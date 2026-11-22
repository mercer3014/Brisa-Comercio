<?php

namespace App\Servicios\Concerns;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Utilidades compartidas por los cargadores para que una carga sea atómica:
 * el DELETE previo y todos los INSERT van en UNA transacción; si algo falla,
 * se revierte y los datos anteriores quedan intactos.
 *
 * - iniciarTransaccion()/confirmarTransaccion()/revertirTransaccion(): soportan
 *   anidamiento (en los comandos por lote con --fresh la carga corre dentro de
 *   una transacción externa y aquí se usa un SAVEPOINT).
 * - insertarLote(): inserta un lote dentro de un SAVEPOINT; si el lote falla
 *   (p.ej. un CHECK >= 0), reintenta fila por fila para aislar las malas y
 *   devuelve cuántas fallaron, sin abortar la transacción completa.
 */
trait CargaTransaccional
{
    private int $nivelTxInicial = 0;

    /** Último mensaje de error de una fila rechazada por la BD (para el log). */
    protected ?string $ultimoErrorFila = null;

    protected function iniciarTransaccion(): void
    {
        $this->nivelTxInicial = DB::transactionLevel();
        DB::beginTransaction();
    }

    protected function confirmarTransaccion(): void
    {
        if (DB::transactionLevel() > $this->nivelTxInicial) {
            DB::commit();
        }
    }

    protected function revertirTransaccion(): void
    {
        if (DB::transactionLevel() > $this->nivelTxInicial) {
            DB::rollBack($this->nivelTxInicial);
        }
        // Las dimensiones creadas dentro de la transacción ya no existen: vaciar
        // los caches en memoria (propiedades $cache*) para no reutilizar IDs inválidos.
        foreach (get_object_vars($this) as $prop => $valor) {
            if (str_starts_with($prop, 'cache') && is_array($valor)) {
                $this->{$prop} = [];
            }
        }
    }

    /** @return int filas que la BD rechazó */
    protected function insertarLote(string $tabla, array $lote): int
    {
        if (empty($lote)) {
            return 0;
        }
        try {
            DB::transaction(fn () => DB::table($tabla)->insert($lote));

            return 0;
        } catch (QueryException $e) {
            $fallidas = 0;
            foreach ($lote as $fila) {
                try {
                    DB::transaction(fn () => DB::table($tabla)->insert($fila));
                } catch (QueryException $e2) {
                    $fallidas++;
                    $this->ultimoErrorFila = mb_substr($e2->getMessage(), 0, 250);
                }
            }

            return $fallidas;
        }
    }

    /**
     * Igual que insertarLote() pero con UPSERT sobre la clave natural
     * ($claves, respaldada por un índice UNIQUE): si la fila ya existe se
     * actualizan $actualizar. Si el lote trae la misma clave dos veces (PG no
     * permite actualizar una fila dos veces en un mismo INSERT ... ON CONFLICT),
     * se reintenta fila por fila y gana la última.
     *
     * @return int filas que la BD rechazó
     */
    protected function upsertLote(string $tabla, array $lote, array $claves, array $actualizar): int
    {
        if (empty($lote)) {
            return 0;
        }
        try {
            DB::transaction(fn () => DB::table($tabla)->upsert($lote, $claves, $actualizar));

            return 0;
        } catch (QueryException $e) {
            $fallidas = 0;
            foreach ($lote as $fila) {
                try {
                    DB::transaction(fn () => DB::table($tabla)->upsert([$fila], $claves, $actualizar));
                } catch (QueryException $e2) {
                    $fallidas++;
                    $this->ultimoErrorFila = mb_substr($e2->getMessage(), 0, 250);
                }
            }

            return $fallidas;
        }
    }
}
