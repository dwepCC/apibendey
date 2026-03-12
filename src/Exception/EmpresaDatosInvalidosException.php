<?php

declare(strict_types=1);

namespace App\Exception;

use InvalidArgumentException;

/**
 * Datos de empresa inválidos (ej. SOL_USER/SOL_PASS obligatorios al crear).
 */
class EmpresaDatosInvalidosException extends InvalidArgumentException
{
    private string $ruc;

    public function __construct(string $ruc, string $message)
    {
        parent::__construct($message);
        $this->ruc = $ruc;
    }

    public function getRuc(): string
    {
        return $this->ruc;
    }
}
