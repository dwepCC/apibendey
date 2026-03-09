<?php

namespace App\Controller\v1;

use App\Service\EmpresasService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * API para gestionar múltiples empresas (multitenant).
 * Permite listar empresas y crear/actualizar desde el frontend.
 *
 * @Route("/api/v1/empresas")
 */
class EmpresasController extends AbstractController
{
    /**
     * @var EmpresasService
     */
    private $empresasService;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    public function __construct(EmpresasService $empresasService, ?LoggerInterface $logger = null)
    {
        $this->empresasService = $empresasService;
        $this->logger = $logger;
    }

    /**
     * Lista todas las empresas registradas en data/empresas.json.
     *
     * @Route("", methods={"GET"})
     */
    public function list(Request $request): JsonResponse
    {
        $empresas = $this->empresasService->getEmpresas();
        return new JsonResponse($empresas);
    }

    /**
     * Crea o actualiza una o varias empresas.
     * Body: { "empresas": { "RUC": { "SOL_USER", "SOL_PASS", "certificate_base64?", "logo_base64?", ... } } }
     * o directamente { "RUC": { ... }, "RUC2": { ... } }.
     *
     * @Route("", methods={"POST"})
     */
    public function createOrUpdate(Request $request): Response
    {
        $content = $request->getContent();
        $data = json_decode($content, true);
        if (!is_array($data)) {
            if ($this->logger) {
                $this->logger->error('[EmpresasController] createOrUpdate: JSON inválido', ['content_preview' => substr($content, 0, 200)]);
            }
            return new JsonResponse(['error' => 'JSON inválido'], Response::HTTP_BAD_REQUEST);
        }

        $empresas = $data['empresas'] ?? $data;
        if (!is_array($empresas)) {
            if ($this->logger) {
                $this->logger->warning('[EmpresasController] createOrUpdate: body no tiene empresas', ['body_keys' => array_keys($data)]);
            }
            return new JsonResponse(['error' => 'Se esperaba un objeto de empresas (RUC => config)'], Response::HTTP_BAD_REQUEST);
        }

        if ($this->logger) {
            $this->logger->info('[EmpresasController] createOrUpdate: recibido', [
                'rucs' => array_keys($empresas),
                'has_empresas_key' => array_key_exists('empresas', $data),
            ]);
        }

        $this->empresasService->addOrUpdateEmpresas($empresas);
        return new JsonResponse(['ok' => true, 'message' => 'Empresas actualizadas']);
    }
}
