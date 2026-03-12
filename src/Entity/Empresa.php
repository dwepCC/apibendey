<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Empresa: datos por RUC (multitenant). Solo credenciales SOL, certificado, logo y ambiente.
 * Las URLs de SUNAT (FE, RE, GUIA) se toman del .env según ambiente (pruebas/produccion).
 *
 * @ORM\Entity(repositoryClass="App\Repository\EmpresaRepository")
 * @ORM\Table(name="empresa")
 */
class Empresa
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=11)
     */
    private string $ruc;

    /** @ORM\Column(type="string", length=100) */
    private string $solUser;

    /** @ORM\Column(type="string", length=255) */
    private string $solPass;

    /** @ORM\Column(type="string", length=255, nullable=true) */
    private ?string $certificate = null;

    /** @ORM\Column(type="string", length=255, nullable=true) */
    private ?string $logo = null;

    /**
     * Ambiente: pruebas | produccion. Por defecto pruebas.
     * @ORM\Column(type="string", length=20, options={"default":"pruebas"})
     */
    private string $ambiente = 'pruebas';

    public function getRuc(): string
    {
        return $this->ruc;
    }

    public function setRuc(string $ruc): self
    {
        $this->ruc = $ruc;
        return $this;
    }

    public function getSolUser(): string
    {
        return $this->solUser;
    }

    public function setSolUser(string $solUser): self
    {
        $this->solUser = $solUser;
        return $this;
    }

    public function getSolPass(): string
    {
        return $this->solPass;
    }

    public function setSolPass(string $solPass): self
    {
        $this->solPass = $solPass;
        return $this;
    }

    public function getCertificate(): ?string
    {
        return $this->certificate;
    }

    public function setCertificate(?string $certificate): self
    {
        $this->certificate = $certificate;
        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): self
    {
        $this->logo = $logo;
        return $this;
    }

    public function getAmbiente(): string
    {
        return $this->ambiente;
    }

    public function setAmbiente(string $ambiente): self
    {
        $this->ambiente = $ambiente;
        return $this;
    }
}
