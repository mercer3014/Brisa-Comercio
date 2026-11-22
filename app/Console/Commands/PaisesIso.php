<?php

namespace App\Console\Commands;

use App\Servicios\ResolverPaisIso;
use Illuminate\Console\Command;

/**
 * Reporte de armonización de países con ISO 3166 y re-vinculación.
 *
 *   php artisan geodata:paises-iso                 # cobertura y países sin ISO
 *   php artisan geodata:paises-iso --revincular    # re-resuelve todo (menos MANUAL)
 *   php artisan geodata:paises-iso --probar="M49:840:Estados Unidos"
 */
class PaisesIso extends Command
{
    protected $signature = 'geodata:paises-iso
        {--revincular : Re-resolver todas las filas de pais (excepto las editadas a mano)}
        {--probar=* : Probar el resolver con ESQUEMA:CODIGO:NOMBRE (ESQUEMA = INE o M49)}';

    protected $description = 'Cobertura ISO 3166 de la tabla pais por fuente y lista de países sin ISO';

    public function handle(ResolverPaisIso $iso): int
    {
        foreach ((array) $this->option('probar') as $p) {
            [$esq, $cod, $nom] = array_pad(explode(':', $p, 3), 3, null);
            $r = $iso->resolver($esq ?: 'M49', $cod !== '' ? $cod : null, $nom);
            $this->line(sprintf('%s | %s | %s => %s (%s, %s)', $esq, $cod, $nom, $r['iso3'] ?? '—', $r['categoria'] ?? 'SIN MATCH', $r['metodo'] ?? '—'));
        }
        if ($this->option('probar')) {
            return self::SUCCESS;
        }

        if ($this->option('revincular')) {
            $n = $iso->revincularTodos();
            $this->info("Re-vinculado. Filas de pais con ISO: {$n}");
        }

        $rep = $iso->reporte();
        $this->table(['Fuente', 'Filas pais', 'Con ISO', 'Zona franca / no det.', 'Sin ISO'],
            array_map(fn ($r) => [$r->fuente_sigla, $r->total, $r->con_iso, $r->no_pais, $r->sin_iso], $rep['cobertura']));

        if ($rep['sin_iso']) {
            $this->warn('Países sin ISO (agregar alias en pais_iso_alias o código en pais_iso_codigo):');
            $this->table(['pais_id', 'Fuente', 'Código', 'Nombre', 'Método'],
                array_map(fn ($r) => [$r->pais_id, $r->fuente_sigla, $r->codigo_pais, $r->nombre_fuente, $r->iso_metodo], $rep['sin_iso']));
        } else {
            $this->info('Todos los países tienen ISO o están marcados como zona franca / no determinado.');
        }

        return self::SUCCESS;
    }
}
