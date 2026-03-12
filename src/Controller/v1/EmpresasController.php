<?php

namespace App\Controller\v1;

use App\Exception\EmpresaDatosInvalidosException;
use App\Exception\EmpresaNoRegistradaException;
use App\Service\EmpresasService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * API para gestionar múltiples empresas (multitenant).
 *
 * Reglas:
 * - RUC siempre obligatorio.
 * - Al registrar una nueva empresa: SOL_USER y SOL_PASS (Clave SOL) obligatorios. Certificado y logo opcionales.
 * - Al actualizar: solo se modifican los campos que envíes; si no envías certificado ni logo se mantienen los actuales.
 * - Para cambiar solo el ambiente (producción ↔ pruebas) usa PATCH /api/v1/empresas/{ruc}/ambiente.
 *
 * @Route("/api/v1/empresas")
 */
class EmpresasController extends AbstractController
{
    private EmpresasService $empresasService;
    private ?LoggerInterface $logger;

    public function __construct(EmpresasService $empresasService, ?LoggerInterface $logger = null)
    {
        $this->empresasService = $empresasService;
        $this->logger = $logger;
    }

    /**
     * Lista todas las empresas registradas en la base de datos.
     *
     * @Route("", methods={"GET"})
     */
    public function list(Request $request): JsonResponse
    {
        $empresas = $this->empresasService->getEmpresas();
        return new JsonResponse($empresas);
    }

    /**
     * Obtiene una empresa por RUC.
     *
     * @Route("/{ruc}", methods={"GET"}, requirements={"ruc": "\d{11}"})
     */
    public function getOne(string $ruc): Response
    {
        $empresas = $this->empresasService->getEmpresas();
        $ruc = trim($ruc);
        if (!isset($empresas[$ruc])) {
            return new JsonResponse(['error' => 'Empresa no registrada para el RUC indicado.', 'ruc' => $ruc], Response::HTTP_NOT_FOUND);
        }
        return new JsonResponse($empresas[$ruc]);
    }

    /**
     * Crea o actualiza una o varias empresas.
     *
     * Formato 1 (una empresa): { "ruc": "20123456789", "SOL_USER": "20123456789MODDATOS", "SOL_PASS": "clave", "ambiente": "pruebas", "certificate_base64": "...", "logo_base64": "..." }
     * Formato 2 (varias): { "empresas": { "20123456789": { "SOL_USER": "...", "SOL_PASS": "...", "ambiente": "pruebas", ... }, ... } }
     *
     * Obligatorios al crear: ruc (o clave del objeto), SOL_USER, SOL_PASS.
     * Opcionales: ambiente (default "pruebas"), certificate_base64, logo_base64.
     * Al actualizar: solo se actualizan los campos enviados; certificado y logo se mantienen si no se envían.
     *
     * @Route("", methods={"POST"})
     */
    public function createOrUpdate(Request $request): Response
    {
        $content = $request->getContent();
        $data = json_decode($content, true);
        if (!is_array($data)) {
            if ($this->logger) {
                $this->logger->error('[EmpresasController] createOrUpdate: JSON inválido', ['content_preview' => substr($content ?? '', 0, 200)]);
            }
            return new JsonResponse(['error' => 'JSON inválido'], Response::HTTP_BAD_REQUEST);
        }

        $empresas = $this->normalizePayload($data);
        if ($empresas === null) {
            return new JsonResponse([
                'error' => 'Se espera un objeto con "ruc" y "SOL_USER", "SOL_PASS", o un objeto "empresas" con RUCs como claves.',
            ], Response::HTTP_BAD_REQUEST);
        }

        foreach ($empresas as $ruc => $config) {
            if (trim((string) $ruc) === '') {
                return new JsonResponse(['error' => 'RUC es obligatorio para cada empresa.'], Response::HTTP_BAD_REQUEST);
            }
        }

        try {
            $this->empresasService->addOrUpdateEmpresas($empresas);
        } catch (EmpresaDatosInvalidosException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'ruc' => $e->getRuc(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(['ok' => true, 'message' => 'Empresa(s) guardada(s) correctamente.']);
    }

    /**
     * Cambia solo el ambiente de la empresa (producción ↔ pruebas).
     * No modifica Clave SOL, certificado ni logo.
     *
     * Body: { "ambiente": "produccion" } o { "ambiente": "pruebas" }
     *
     * @Route("/{ruc}/ambiente", methods={"PATCH"}, requirements={"ruc": "\d{11}"})
     */
    public function updateAmbiente(string $ruc, Request $request): Response
    {
        $ruc = trim($ruc);
        $content = $request->getContent();
        $data = is_string($content) && $content !== '' ? json_decode($content, true) : [];
        $ambiente = is_array($data) && isset($data['ambiente']) ? trim((string) $data['ambiente']) : '';

        if ($ambiente === '') {
            return new JsonResponse([
                'error' => 'El cuerpo debe incluir "ambiente" con valor "pruebas" o "produccion".',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->empresasService->updateAmbiente($ruc, $ambiente);
        } catch (EmpresaNoRegistradaException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'ruc' => $e->getRuc()], Response::HTTP_NOT_FOUND);
        } catch (EmpresaDatosInvalidosException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'ruc' => $e->getRuc()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(['ok' => true, 'ruc' => $ruc, 'ambiente' => $ambiente]);
    }

    /**
     * Convierte body en array [ RUC => config ].
     * Acepta: { "ruc": "20...", "SOL_USER": "...", ... } o { "empresas": { "20...": { ... } } }.
     *
     * @return array<string, array>|null
     */
    private function normalizePayload(array $data): ?array
    {
        if (isset($data['empresas']) && is_array($data['empresas'])) {
            return $data['empresas'];
        }
        if (isset($data['ruc']) && (is_string($data['ruc']) || is_int($data['ruc']))) {
            $ruc = trim((string) $data['ruc']);
            if ($ruc === '') {
                return null;
            }
            $config = $data;
            unset($config['ruc']);
            return [$ruc => $config];
        }
        return null;
    }
}
