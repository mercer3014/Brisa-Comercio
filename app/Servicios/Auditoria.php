<?php

namespace App\Servicios;

use App\Models\BitacoraAuditoria;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Registro centralizado de la bitacora de auditoria (bitacora_auditoria).
 * Captura usuario, accion, entidad, valores anterior/nuevo (JSONB) e IP.
 */
class Auditoria
{
    public static function registrar(
        string $accion,
        ?string $entidad = null,
        ?string $registro = null,
        ?array $anteriores = null,
        ?array $nuevos = null
    ): void {
        BitacoraAuditoria::create([
            'usuario_id'         => Auth::id(),
            'accion'             => $accion,
            'entidad_afectada'   => $entidad,
            'registro_afectado'  => $registro,
            'valores_anteriores' => $anteriores,
            'valores_nuevos'     => $nuevos,
            'ip_origen'          => Request::ip(),
            'fecha_hora'         => now(),
        ]);
    }
}
