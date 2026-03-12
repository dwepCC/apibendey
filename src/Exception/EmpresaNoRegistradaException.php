<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

/**
 * Se lanza cuando se solicita operar con un RUC que no está registrado en la base de datos (multiempresa).
 */
class EmpresaNoRegistradaException extends RuntimeException
{
    private string $ruc;

    public function __construct(string $ruc, string $message = 'Empresa no registrada para el RUC indicado.')
    {
        parent::__construct($message);
        $this->ruc = $ruc;
    }

    public function getRuc(): string
    {
        return $this->ruc;
    }
}
