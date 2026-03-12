<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Exception\EmpresaDatosInvalidosException;
use App\Exception\EmpresaNoRegistradaException;
use App\Repository\EmpresaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Servicio para gestionar múltiples empresas (persistencia en base de datos).
 * Reglas: RUC siempre obligatorio. Al crear: SOL_USER y SOL_PASS obligatorios.
 * Al actualizar: solo se modifican los campos enviados; si no se envían certificado/logo se mantienen los actuales.
 */
class EmpresasService
{
    private const ALLOWED_KEYS = [
        'SOL_USER',
        'SOL_PASS',
        'certificate',
        'logo',
        'ambiente',
    ];

    private const AMBIENTES_VALIDOS = ['pruebas', 'produccion'];

    private EmpresaRepository $empresaRepository;
    private EntityManagerInterface $em;
    private string $dataPath;
    private ?LoggerInterface $logger;

    public function __construct(
        EmpresaRepository $empresaRepository,
        EntityManagerInterface $em,
        string $dataPath,
        ?LoggerInterface $logger = null
    ) {
        $this->empresaRepository = $empresaRepository;
        $this->em = $em;
        $this->dataPath = $dataPath;
        $this->logger = $logger;
    }

    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->$level('[EmpresasService] ' . $message, $context);
        }
    }

    /**
     * Obtiene todas las empresas desde la base de datos.
     *
     * @return array<string, array> RUC => configuración (incluye clave ambiente)
     */
    public function getEmpresas(): array
    {
        return $this->empresaRepository->getCompaniesArray();
    }

    public function saveCertificate(string $ruc, string $content): bool
    {
        $ruc = trim($ruc);
        if ($ruc === '') {
            $this->log('warning', 'saveCertificate: RUC vacío, no se guarda');
            return false;
        }
        $this->ensureDataDirectoryExists();
        $path = $this->dataPath . DIRECTORY_SEPARATOR . $ruc . '-cert.pem';
        $ok = file_put_contents($path, $content) !== false;
        $this->log($ok ? 'info' : 'error', 'saveCertificate', [
            'ruc' => $ruc,
            'path' => $path,
            'success' => $ok,
        ]);
        return $ok;
    }

    public function saveLogo(string $ruc, string $content): bool
    {
        $ruc = trim($ruc);
        if ($ruc === '') {
            $this->log('warning', 'saveLogo: RUC vacío, no se guarda');
            return false;
        }
        $this->ensureDataDirectoryExists();
        $path = $this->dataPath . DIRECTORY_SEPARATOR . $ruc . '-logo.png';
        $ok = file_put_contents($path, $content) !== false;
        $this->log($ok ? 'info' : 'error', 'saveLogo', ['ruc' => $ruc, 'success' => $ok]);
        return $ok;
    }

    private function ensureDataDirectoryExists(): void
    {
        if (!is_dir($this->dataPath)) {
            mkdir($this->dataPath, 0755, true);
        }
    }

    public static function decodeBase64Content(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (strpos($value, 'base64,') !== false) {
            $value = substr($value, strpos($value, 'base64,') + 7);
        }
        $decoded = base64_decode($value, true);
        return $decoded !== false ? $decoded : null;
    }

    /**
     * Agrega o actualiza empresas en la base de datos.
     * - RUC siempre obligatorio.
     * - Al crear: SOL_USER y SOL_PASS obligatorios. Certificado y logo opcionales.
     * - Al actualizar: solo se modifican los campos enviados; si no se envían certificado/logo se mantienen los actuales.
     *
     * @param array<string, array> $empresas RUC => [ SOL_USER?, SOL_PASS?, ambiente?, certificate_base64?, logo_base64?, ... ]
     * @throws EmpresaDatosInvalidosException Si al crear falta SOL_USER o SOL_PASS, o RUC vacío
     */
    public function addOrUpdateEmpresas(array $empresas): void
    {
        $empresas = array_filter($empresas, function ($v, $k) {
            return (is_string($k) || is_int($k)) && trim((string) $k) !== '' && is_array($v);
        }, ARRAY_FILTER_USE_BOTH);
        if (empty($empresas)) {
            $this->log('warning', 'addOrUpdateEmpresas: lista vacía o inválida');
            return;
        }

        $current = $this->getEmpresas();

        foreach ($empresas as $ruc => $config) {
            $ruc = trim((string) $ruc);
            if ($ruc === '' || !is_array($config)) {
                continue;
            }

            $isUpdate = isset($current[$ruc]);

            $certBase64 = $config['certificate_base64'] ?? $config['certificateBase64'] ?? null;
            $logoBase64 = $config['logo_base64'] ?? $config['logoBase64'] ?? null;

            if ($certBase64 !== null && $certBase64 !== '') {
                $decoded = self::decodeBase64Content($certBase64);
                if ($decoded !== null) {
                    $certFile = $ruc . '-cert.pem';
                    $this->ensureDataDirectoryExists();
                    if (file_put_contents($this->dataPath . DIRECTORY_SEPARATOR . $certFile, $decoded) !== false) {
                        $config['certificate'] = $certFile;
                    }
                }
            }
            if ($logoBase64 !== null && $logoBase64 !== '') {
                $decoded = self::decodeBase64Content($logoBase64);
                if ($decoded !== null) {
                    $logoFile = $ruc . '-logo.png';
                    $this->ensureDataDirectoryExists();
                    if (file_put_contents($this->dataPath . DIRECTORY_SEPARATOR . $logoFile, $decoded) !== false) {
                        $config['logo'] = $logoFile;
                    }
                }
            }

            if ($isUpdate) {
                $entry = array_merge($current[$ruc], []);
                foreach (self::ALLOWED_KEYS as $key) {
                    if (array_key_exists($key, $config) && $config[$key] !== '' && $config[$key] !== null) {
                        $entry[$key] = $config[$key];
                    }
                }
                if (!isset($entry['certificate'])) {
                    $entry['certificate'] = $current[$ruc]['certificate'] ?? null;
                }
                if (!isset($entry['logo'])) {
                    $entry['logo'] = $current[$ruc]['logo'] ?? null;
                }
            } else {
                $solUser = trim((string) ($config['SOL_USER'] ?? ''));
                $solPass = trim((string) ($config['SOL_PASS'] ?? ''));
                if ($solUser === '' || $solPass === '') {
                    throw new EmpresaDatosInvalidosException($ruc, 'Al registrar una nueva empresa son obligatorios SOL_USER y SOL_PASS (Clave SOL).');
                }
                $entry = [
                    'SOL_USER' => $solUser,
                    'SOL_PASS' => $solPass,
                    'certificate' => $config['certificate'] ?? null,
                    'logo' => $config['logo'] ?? null,
                    'ambiente' => $this->normalizeAmbiente($config['ambiente'] ?? 'pruebas'),
                ];
            }

            if (!isset($entry['ambiente']) || $entry['ambiente'] === '') {
                $entry['ambiente'] = 'pruebas';
            }
            $entry['ambiente'] = $this->normalizeAmbiente($entry['ambiente']);

            $entity = $this->empresaRepository->findByRuc($ruc);
            if ($entity === null) {
                $entity = new Empresa();
                $entity->setRuc($ruc);
            }

            $entity->setSolUser((string) ($entry['SOL_USER'] ?? ''));
            $entity->setSolPass((string) ($entry['SOL_PASS'] ?? ''));
            $entity->setCertificate($entry['certificate'] ?? null);
            $entity->setLogo($entry['logo'] ?? null);
            $entity->setAmbiente((string) $entry['ambiente']);

            $this->em->persist($entity);
        }

        $this->em->flush();
        $this->log('info', 'addOrUpdateEmpresas: empresas guardadas en BD', ['count' => count($empresas)]);
    }

    /**
     * Cambia solo el ambiente de la empresa (producción ↔ pruebas).
     * Al pasar a "produccion" se valida que la empresa tenga usuario SOL, contraseña SOL y certificado configurados (y que el archivo del certificado exista).
     *
     * @throws EmpresaNoRegistradaException Si el RUC no está en la BD
     * @throws EmpresaDatosInvalidosException Si el ambiente no es válido o faltan datos para producción
     */
    public function updateAmbiente(string $ruc, string $ambiente): void
    {
        $ruc = trim($ruc);
        if ($ruc === '') {
            throw new EmpresaDatosInvalidosException('', 'RUC es obligatorio.');
        }
        $entity = $this->empresaRepository->findByRuc($ruc);
        if ($entity === null) {
            throw new EmpresaNoRegistradaException($ruc);
        }
        $ambiente = $this->normalizeAmbiente($ambiente);

        if ($ambiente === 'produccion') {
            $faltan = [];
            if (trim($entity->getSolUser()) === '') {
                $faltan[] = 'usuario SOL (SOL_USER)';
            }
            if (trim($entity->getSolPass()) === '') {
                $faltan[] = 'contraseña SOL (SOL_PASS)';
            }
            $certFile = $entity->getCertificate();
            if ($certFile === null || trim($certFile) === '') {
                $faltan[] = 'certificado digital';
            } else {
                $certPath = $this->dataPath . DIRECTORY_SEPARATOR . $certFile;
                if (!is_file($certPath)) {
                    $faltan[] = 'archivo del certificado (' . $certFile . ')';
                }
            }
            if ($faltan !== []) {
                throw new EmpresaDatosInvalidosException(
                    $ruc,
                    'Para pasar a producción la empresa debe tener usuario SOL, contraseña SOL y certificado digital configurados. Faltan: ' . implode(', ', $faltan) . '.'
                );
            }
        }

        $entity->setAmbiente($ambiente);
        $this->em->flush();
        $this->log('info', 'updateAmbiente', ['ruc' => $ruc, 'ambiente' => $ambiente]);
    }

    private function normalizeAmbiente(string $ambiente): string
    {
        $a = strtolower(trim($ambiente));
        if (!in_array($a, self::AMBIENTES_VALIDOS, true)) {
            return 'pruebas';
        }
        return $a;
    }
}

