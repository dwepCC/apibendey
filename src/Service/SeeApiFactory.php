<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\EmpresaNoRegistradaException;
use Greenter\Api;

class SeeApiFactory
{
    private ConfigProviderInterface $config;
    private ConfigProviderInterface $fileProvider;
    private FileDataReader $fileReader;
    private Api $see;

    /**
     * SeeFactory constructor.
     * @param ConfigProviderInterface $config
     * @param ConfigProviderInterface $fileProvider
     * @param FileDataReader $fileReader
     * @param Api $see
     */
    public function __construct(ConfigProviderInterface $config, ConfigProviderInterface $fileProvider, FileDataReader $fileReader, Api $see)
    {
        $this->config = $config;
        $this->fileProvider = $fileProvider;
        $this->fileReader = $fileReader;
        $this->see = $see;
    }

    /**
     * Construye Api con credenciales de la empresa en BD (multiempresa).
     * No usa .env como respaldo: si el RUC no está registrado, lanza EmpresaNoRegistradaException.
     *
     * @param string|null $ruc RUC de la empresa (obligatorio en modo multiempresa)
     * @return Api
     * @throws EmpresaNoRegistradaException Si el RUC está vacío o no existe en la BD
     */
    public function build(?string $ruc): Api
    {
        $ruc = $ruc !== null ? trim((string) $ruc) : '';
        if ($ruc === '') {
            throw new EmpresaNoRegistradaException('', 'RUC requerido. La aplicación opera en modo multiempresa con datos en base de datos.');
        }
        if (!$this->configureSeeWithRuc($ruc)) {
            throw new EmpresaNoRegistradaException($ruc);
        }

        return $this->see;
    }

    private function configureSeeWithRuc(string $ruc): bool
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
        if (!is_array($companies) || !array_key_exists($ruc, $companies)) {
            return false;
        }

        $config = $companies[$ruc];
        list ($rucPart, $user) = $this->getRucAndUser($config['SOL_USER']);
        $this->see->setClaveSOL($rucPart, $user, $config['SOL_PASS']);
        $this->see->setCertificate($this->fileReader->getContents($config['certificate']));
        $this->see->setApiCredentials($config['CLIENT_ID'], $config['CLIENT_SECRET']);

        return true;
    }

    /**
     * @deprecated Solo para compatibilidad; en multiempresa no se usa fallback a .env
     */
    private function configureSeeWithEnv()
    {
        list ($ruc, $user) = $this->getRucAndUser($this->config->get('SOL_USER'));
        $this->see->setClaveSOL($ruc, $user, $this->config->get('SOL_PASS'));
        $this->see->setApiCredentials($this->config->get('CLIENT_ID'), $this->config->get('CLIENT_SECRET'));
        $this->see->setCertificate($this->fileProvider->get('certificate'));
    }

    private function getRucAndUser(string $username): array
    {
        $ruc = substr($username, 0, 11);
        $user = substr($username, 11);

        return [$ruc, $user];
    }
}
