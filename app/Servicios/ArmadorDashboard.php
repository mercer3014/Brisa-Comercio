<?php

namespace App\Servicios;

/**
 * Arma la respuesta completa del dashboard admin para cualquier organización,
 * eligiendo el agregador según su arquitectura (INE = microdato, ALADI =
 * rankings, MERCOSUR = series por zona/producto, FAOSTAT = índices). Vive
 * como servicio para que el controlador y geodata:calentar-cache construyan
 * EXACTAMENTE el mismo payload.
 */
class ArmadorDashboard
{
    public function __construct(
        private AgregadorDashboard $agg,
        private AgregadorDashboardMercosur $aggMercosur,
        private AgregadorDashboardAladi $aggAladi,
        private AgregadorDashboardFaostat $aggFaostat,
    ) {
    }

    public function armar(int $org, ?int $gestion): array
    {
        if ($org === 2 || $org === 3 || $org === 4) {
            $servicio = match ($org) {
                3 => $this->aggMercosur,
                4 => $this->aggFaostat,
                default => $this->aggAladi,
            };

            return [
                'kpis'                      => $servicio->kpis($gestion),
                'evolucion_mensual'         => $servicio->evolucionMensual($gestion),
                'evolucion_anual'           => $servicio->evolucionAnual(),
                'top_paises'                => $servicio->topPaises($gestion),
                'top_productos'             => $servicio->topProductos($gestion),
                'distribucion_zona'         => $servicio->distribucionZona($gestion),
                'distribucion_departamento' => $servicio->distribucionDepartamento(),
                'participacion_pais'        => $servicio->participacionPais($gestion),
                'distribucion_medio'        => $servicio->distribucionMedio(),
            ];
        }

        return [
            'kpis'                     => $this->agg->kpis($org, $gestion),
            'evolucion_mensual'        => $this->agg->evolucionMensual($org, $gestion),
            'evolucion_anual'          => $this->agg->evolucionAnual($org),
            'top_paises'               => $this->agg->topPaises($org, $gestion),
            'top_productos'            => $this->agg->topProductos($org, $gestion),
            'distribucion_zona'        => $this->agg->distribucionZona($org, $gestion),
            'distribucion_departamento' => $this->agg->distribucionDepartamento($org, $gestion),
            'participacion_pais'       => $this->agg->participacionPais($org, $gestion),
            'distribucion_medio'       => $this->agg->distribucionMedio($org, $gestion),
        ];
    }
}
