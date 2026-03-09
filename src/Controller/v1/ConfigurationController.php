<?php
/**
 * Created by PhpStorm.
 * User: Administrador
 * Date: 21/02/2018
 * Time: 03:30 PM
 */

namespace App\Controller\v1;

use App\Service\ConfigProviderInterface;
use App\Service\EmpresasService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ConfigurationController
 *
 * Certificado y logo se guardan siempre por RUC: data/{ruc}-cert.pem y data/{ruc}-logo.png,
 * y se actualiza data/empresas.json (mismo criterio que empresas.json). Es obligatorio
 * enviar "ruc" cuando se suben certificate o logo.
 *
 * @Route("/api/v1/configuration")
 */
class ConfigurationController extends AbstractController
{
    /**
     * @var ConfigProviderInterface
     */
    private $fileStore;

    /**
     * @var EmpresasService
     */
    private $empresasService;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    public function __construct(ConfigProviderInterface $fileStore, EmpresasService $empresasService, ?LoggerInterface $logger = null)
    {
        $this->fileStore = $fileStore;
        $this->empresasService = $empresasService;
        $this->logger = $logger;
    }

    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->$level('[ConfigurationController] ' . $message, $context);
        }
    }

    /**
     * @Route("/", methods={"POST"})
     *
     * Certificado y logo siempre por RUC: {ruc}-cert.pem y {ruc}-logo.png en data/,
     * y se actualiza data/empresas.json. Si envías certificate o logo sin "ruc" → 400.
     *
     * @param Request $request
     * @return Response
     */
    public function config(Request $request) : Response
    {
        $rawContent = $request->getContent();
        $data = json_decode($rawContent, true);

        $requestKeys = is_array($data) ? array_keys($data) : [];
        $this->log('info', 'config: POST recibido', [
            'body_keys' => $requestKeys,
            'has_ruc' => is_array($data) && isset($data['ruc']),
            'ruc_value' => is_array($data) && isset($data['ruc']) ? trim((string) $data['ruc']) : null,
            'has_certificate' => is_array($data) && !empty($data['certificate']),
            'has_certificate_base64' => is_array($data) && !empty($data['certificate_base64']),
            'has_logo' => is_array($data) && !empty($data['logo']),
            'has_logo_base64' => is_array($data) && !empty($data['logo_base64']),
            'certificate_length' => is_array($data) && isset($data['certificate']) ? strlen((string) $data['certificate']) : 0,
            'logo_length' => is_array($data) && isset($data['logo']) ? strlen((string) $data['logo']) : 0,
        ]);

        if (!is_array($data)) {
            $this->log('error', 'config: body no es JSON válido');
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        $hasCertOrLogo = !empty($data['certificate']) || !empty($data['logo'])
            || !empty($data['certificate_base64']) || !empty($data['logo_base64']);
        $ruc = isset($data['ruc']) ? trim((string) $data['ruc']) : null;

        if ($hasCertOrLogo && ($ruc === null || $ruc === '')) {
            $this->log('warning', 'config: se envió certificate o logo pero no "ruc" → 400');
            return new JsonResponse([
                'error' => 'Cuando envías certificate o logo debes enviar "ruc" para guardar como {ruc}-cert.pem y {ruc}-logo.png (multi-empresa).',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($ruc !== null && $ruc !== '') {
            // Guardar como {ruc}-cert.pem y {ruc}-logo.png y actualizar empresas.json
            $certContent = !empty($data['certificate']) ? EmpresasService::decodeBase64Content($data['certificate'])
                : (!empty($data['certificate_base64']) ? EmpresasService::decodeBase64Content($data['certificate_base64']) : null);
            $logoContent = !empty($data['logo']) ? EmpresasService::decodeBase64Content($data['logo'])
                : (!empty($data['logo_base64']) ? EmpresasService::decodeBase64Content($data['logo_base64']) : null);

            $this->log('info', 'config: decode base64', [
                'ruc' => $ruc,
                'cert_decoded_ok' => $certContent !== null,
                'cert_decoded_bytes' => $certContent !== null ? strlen($certContent) : 0,
                'logo_decoded_ok' => $logoContent !== null,
                'logo_decoded_bytes' => $logoContent !== null ? strlen($logoContent) : 0,
            ]);

            if ((!empty($data['certificate']) || !empty($data['certificate_base64'])) && $certContent === null) {
                $this->log('error', 'config: certificate no es base64 válido');
                return new JsonResponse([
                    'error' => 'El campo certificate no es base64 válido. Envía el archivo en base64 o con formato data URL (data:...;base64,...).',
                ], Response::HTTP_BAD_REQUEST);
            }
            if ((!empty($data['logo']) || !empty($data['logo_base64'])) && $logoContent === null) {
                $this->log('error', 'config: logo no es base64 válido');
                return new JsonResponse([
                    'error' => 'El campo logo no es base64 válido. Envía la imagen en base64 o con formato data URL (data:...;base64,...).',
                ], Response::HTTP_BAD_REQUEST);
            }

            if ($certContent !== null) {
                $saved = $this->empresasService->saveCertificate($ruc, $certContent);
                if (!$saved) {
                    $this->log('error', 'config: falló guardar certificado');
                    return new JsonResponse([
                        'error' => 'No se pudo guardar el certificado en data/. Comprueba permisos de escritura.',
                    ], Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            }
            if ($logoContent !== null) {
                $saved = $this->empresasService->saveLogo($ruc, $logoContent);
                if (!$saved) {
                    $this->log('error', 'config: falló guardar logo');
                    return new JsonResponse([
                        'error' => 'No se pudo guardar el logo en data/. Comprueba permisos de escritura.',
                    ], Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            }

            $config = [
                'certificate' => $ruc . '-cert.pem',
                'logo' => $ruc . '-logo.png',
            ];
            if (!empty($data['SOL_USER'])) {
                $config['SOL_USER'] = $data['SOL_USER'];
            }
            if (!empty($data['SOL_PASS'])) {
                $config['SOL_PASS'] = $data['SOL_PASS'];
            }
            foreach (['FE_URL', 'RE_URL', 'GUIA_URL', 'AUTH_URL', 'API_URL', 'CLIENT_ID', 'CLIENT_SECRET'] as $key) {
                if (!empty($data[$key])) {
                    $config[$key] = $data[$key];
                }
            }
            $this->log('info', 'config: llamando addOrUpdateEmpresas', ['ruc' => $ruc]);
            $this->empresasService->addOrUpdateEmpresas([$ruc => $config]);
            $this->log('info', 'config: OK, empresa guardada');
            return new Response();
        }

        // Sin ruc: otras claves (p. ej. companies)
        $this->log('info', 'config: sin ruc, guardando otras claves', ['keys' => array_keys($data)]);
        foreach ($data as $key => $value) {
            if ($key === 'certificate' || $key === 'logo' || $key === 'ruc' || empty($value)) {
                continue;
            }
            $decoded = base64_decode($value, true);
            $fileContent = ($decoded !== false) ? $decoded : $value;
            $this->fileStore->store($key, $fileContent);
            $this->log('info', 'config: store key', ['key' => $key]);
        }

        return new Response();
    }
}