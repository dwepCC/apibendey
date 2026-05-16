<?php

namespace App\Service;

use App\Exception\EmpresaNoRegistradaException;
use Greenter\Model\Despatch\Despatch;
use Greenter\Model\Perception\Perception;
use Greenter\Model\Retention\Retention;
use Greenter\Model\Voided\Reversion;
use Greenter\See;

class SeeFactory
{
    /**
     * @var ConfigProviderInterface
     */
    private $config;

    /**
     * @var ConfigProviderInterface
     */
    private $fileProvider;

    /**
     * @var FileDataReader
     */
    private $fileReader;

    /**
     * @var See
     */
    private $see;

    /**
     * SeeFactory constructor.
     *
     * @param ConfigProviderInterface $config
     * @param ConfigProviderInterface $fileProvider
     * @param FileDataReader          $fileReader
     * @param See                     $see
     */
    public function __construct(ConfigProviderInterface $config, ConfigProviderInterface $fileProvider, FileDataReader $fileReader, See $see)
    {
        $this->config = $config;
        $this->fileProvider = $fileProvider;
        $this->fileReader = $fileReader;
        $this->see = $see;
    }

    /**
     * Construye See con credenciales y certificado de la empresa en BD (multiempresa).
     * No usa .env como respaldo: si el RUC no está registrado, lanza EmpresaNoRegistradaException.
     *
     * @param string      $class Clase del documento (Invoice, Despatch, etc.)
     * @param string|null $ruc   RUC de la empresa (obligatorio en modo multiempresa)
     *
     * @throws EmpresaNoRegistradaException Si el RUC está vacío o no existe en la BD
     */
    public function build(string $class, ?string $ruc): See
    {
        $ruc = $ruc !== null ? trim((string) $ruc) : '';
        if ($ruc === '') {
            throw new EmpresaNoRegistradaException('', 'RUC requerido. La aplicación opera en modo multiempresa con datos en base de datos.');
        }
        if (!$this->configureSeeWithRuc($ruc, $class)) {
            throw new EmpresaNoRegistradaException($ruc);
        }

        return $this->see;
    }

    private function configureSeeWithRuc(string $ruc, string $class): bool
    {
        $ruc = trim((string) $ruc);
        if ($ruc === '') {
            return false;
        }

        $jsonCompanies = $this->fileProvider->get('companies');
        if (empty($jsonCompanies)) {
            return false;
        }

        $companies = json_decode($jsonCompanies, true);
        if (!is_array($companies)) {
            return false;
        }

        if (!array_key_exists($ruc, $companies)) {
            return false;
        }

        $config = $companies[$ruc];
        $this->see->setCredentials($config['SOL_USER'], $config['SOL_PASS']);
        $this->see->setCertificate($this->fileReader->getContents($config['certificate']));
        $this->see->setService($this->getUrlService($class, $config));

        return true;
    }

    /**
     * @deprecated Solo para compatibilidad; en multiempresa no se usa fallback a .env
     */
    private function configureSeeWithEnv(string $class)
    {
        $this->see->setCredentials($this->config->get('SOL_USER'), $this->config->get('SOL_PASS'));
        $this->see->setCertificate($this->fileProvider->get('certificate'));
        $this->see->setService($this->getUrlService($class));
    }

    /**
     * Devuelve la URL del servicio SUNAT según el tipo de comprobante y el ambiente de la empresa.
     * - Ambiente "produccion" → usa PRO_FE_URL, PRO_RE_URL, PRO_GUIA_URL del .env
     * - Ambiente "pruebas" (o por defecto) → usa FE_URL, RE_URL, GUIA_URL del .env
     *
     * @param array<string, mixed> $config
     *
     * @return string|null
     */
    private function getUrlService($className, $config = [])
    {
        $key = 'FE_URL';
        switch ($className) {
            case Perception::class:
            case Retention::class:
            case Reversion::class:
                $key = 'RE_URL';
                break;
            case Despatch::class:
                $key = 'GUIA_URL';
                break;
        }

        if (!empty($config['ambiente']) && $config['ambiente'] === 'produccion') {
            $proKey = 'PRO_' . $key;
            $url = $this->config->get($proKey);
            if ($url !== '' && $url !== null) {
                return $url;
            }
        }

        return $this->config->get($key);
    }
}
