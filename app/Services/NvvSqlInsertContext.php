<?php

namespace App\Services;

/**
 * Estado del insert NVV en SQL Server para poder revertir si falla un paso posterior.
 */
class NvvSqlInsertContext
{
    public int $idMaeedo = 0;

    public string $nudo = '';

    public bool $maeedoInserted = false;

    public bool $maeddoInserted = false;

    public bool $stockUpdated = false;

    /** @var array<string, float> codigo producto => cantidad */
    public array $productosCantidades = [];

    public bool $maeedoobInserted = false;

    public bool $maedtliInserted = false;

    public bool $confiestUpdated = false;

    public ?string $confiestNvvAnterior = null;

    /** @var int[] */
    public array $stockComprometidoIds = [];

    public function tieneDatosEnErp(): bool
    {
        return $this->maeedoInserted || $this->maeddoInserted || $this->stockUpdated
            || $this->maeedoobInserted || $this->maedtliInserted;
    }
}
