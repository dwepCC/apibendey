<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Servicio para gestionar múltiples empresas en data/empresas.json.
 * Permite listar, crear y actualizar empresas para uso multitenant.
 */
class EmpresasService
{
    /**
     * Claves que se aceptan en cada empresa (sin incluir certificate_base64/logo_base64).
     */
    private const ALLOWED_KEYS = [
        'SOL_USER',
        'SOL_PASS',
        'certificate',
        'logo',
        'FE_URL',
        'RE_URL',
        'GUIA_URL',
        'AUTH_URL',
        'API_URL',
        'CLIENT_ID',
        'CLIENT_SECRET',
    ];

    /**
     * @var ConfigProviderInterface
     */
    private $fileProvider;

    /**
     * @var string
     */
    private $dataPath;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    public function __construct(ConfigProviderInterface $fileProvider, string $dataPath, ?LoggerInterface $logger = null)
    {
        $this->fileProvider = $fileProvider;
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
     * Obtiene todas las empresas del archivo empresas.json.
     *
     * @return array<string, array> RUC => configuración
     */
    public function getEmpresas(): array
    {
        $json = $this->fileProvider->get('companies');
        $path = $this->dataPath . DIRECTORY_SEPARATOR . 'empresas.json';
        $this->log('info', 'getEmpresas: leyendo empresas.json', ['path' => $path, 'exists' => file_exists($path), 'content_length' => is_string($json) ? strlen($json) : 0]);
        if ($json === '' || $json === null) {
            return [];
        }
        $data = json_decode($json, true);
        $result = is_array($data) ? $data : [];
        $this->log('info', 'getEmpresas: empresas cargadas', ['count' => count($result)]);
        return $result;
    }

    /**
     * Guarda el contenido del certificado para un RUC en data/{ruc}-cert.pem.
     *
     * @return bool true si se guardó correctamente
     */
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
            'content_bytes' => strlen($content),
            'success' => $ok,
            'data_dir_writable' => is_writable($this->dataPath),
        ]);
        return $ok;
    }

    /**
     * Guarda el contenido del logo para un RUC en data/{ruc}-logo.png.
     *
     * @return bool true si se guardó correctamente
     */
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
        $this->log($ok ? 'info' : 'error', 'saveLogo', [
            'ruc' => $ruc,
            'path' => $path,
            'content_bytes' => strlen($content),
            'success' => $ok,
            'data_dir_writable' => is_writable($this->dataPath),
        ]);
        return $ok;
    }

    private function ensureDataDirectoryExists(): void
    {
        if (!is_dir($this->dataPath)) {
            $this->log('info', 'ensureDataDirectoryExists: creando directorio data', ['path' => $this->dataPath]);
            mkdir($this->dataPath, 0755, true);
        }
    }

    /**
     * Extrae contenido base64 puro; si viene con prefijo data URL (data:...;base64,XXX) lo quita.
     */
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
     * Agrega o actualiza empresas. Si se envían certificate_base64 o logo_base64,
     * se guardan en data/{ruc}-cert.pem y data/{ruc}-logo.png.
     *
     * @param array<string, array> $empresas RUC => [ SOL_USER, SOL_PASS, certificate_base64?, logo_base64?, ... ]
     */
    public function addOrUpdateEmpresas(array $empresas): void
    {
        $keysReceived = array_keys($empresas);
        $empresas = array_filter($empresas, function ($v, $k) {
            return (is_string($k) || is_int($k)) && trim((string) $k) !== '' && is_array($v);
        }, ARRAY_FILTER_USE_BOTH);
        if (empty($empresas)) {
            $this->log('warning', 'addOrUpdateEmpresas: lista de empresas vacía o inválida, no se escribe empresas.json', ['keys_received' => $keysReceived]);
            return;
        }

        $current = $this->getEmpresas();
        $this->log('info', 'addOrUpdateEmpresas: inicio', ['rucs_to_update' => array_keys($empresas), 'current_empresas_count' => count($current)]);

        foreach ($empresas as $ruc => $config) {
            $ruc = trim((string) $ruc);
            if ($ruc === '' || !is_array($config)) {
                continue;
            }

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

            $entry = [];
            foreach (self::ALLOWED_KEYS as $key) {
                if (array_key_exists($key, $config) && $config[$key] !== '' && $config[$key] !== null) {
                    $entry[$key] = $config[$key];
                }
            }
            if (!isset($entry['certificate']) && isset($current[$ruc]['certificate'])) {
                $entry['certificate'] = $current[$ruc]['certificate'];
            }
            if (!isset($entry['logo']) && isset($current[$ruc]['logo'])) {
                $entry['logo'] = $current[$ruc]['logo'];
            }

            $current[$ruc] = array_merge($current[$ruc] ?? [], $entry);
        }

        $jsonContent = json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $empresasJsonPath = $this->dataPath . DIRECTORY_SEPARATOR . 'empresas.json';
        $this->fileProvider->store('companies', $jsonContent);
        $this->log('info', 'addOrUpdateEmpresas: empresas.json escrito', [
            'path' => $empresasJsonPath,
            'bytes_written' => strlen($jsonContent),
            'empresas_count' => count($current),
            'file_exists_after' => file_exists($empresasJsonPath),
        ]);
    }
}
