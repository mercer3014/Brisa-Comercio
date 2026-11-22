<?php

namespace App\Servicios;

use App\Support\PlantillasCarga;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Genera la plantilla Excel (.xlsx) de una entidad/flujo con OpenSpout.
 *
 * Hojas:
 *  1. "Datos"         -> SOLO la fila de cabeceras exacta que reconoce el cargador.
 *                        Es la hoja que se lee al cargar (los lectores usan la
 *                        primera hoja), por eso va vacía: aquí se pegan los datos.
 *  2. "Ejemplo"       -> las mismas cabeceras + 2 filas de EJEMPLO (no se cargan).
 *  3. "Instrucciones" -> significado, formato y unidad de cada columna + notas.
 */
class GeneradorPlantilla
{
    public function escribir(string $sigla, string $tipoFlujo, string $destino): array
    {
        $def = PlantillasCarga::definicion($sigla, $tipoFlujo);
        if (! $def) {
            throw new \InvalidArgumentException("No hay plantilla para {$sigla} / {$tipoFlujo}.");
        }

        $cabeceras = array_column($def['columnas'], 'nombre');
        $esTexto = array_map(fn ($c) => str_contains(mb_strtoupper($c['formato']), 'TEXTO'), $def['columnas']);

        $estCab = (new Style())->setFontBold()->setFontColor(Color::WHITE)->setBackgroundColor('6B1730');
        $estOblig = (new Style())->setFontBold()->setFontColor(Color::WHITE)->setBackgroundColor('15294A');
        $estTexto = (new Style())->setFormat('@');
        $estTitulo = (new Style())->setFontBold()->setFontSize(14)->setFontColor('6B1730');
        $estAviso = (new Style())->setFontBold()->setFontColor('B45309');
        $estNegrita = (new Style())->setFontBold();
        $estAjuste = (new Style())->setShouldWrapText();

        $filaCabecera = function () use ($def, $estCab, $estOblig) {
            $celdas = [];
            foreach ($def['columnas'] as $col) {
                $celdas[] = Cell::fromValue($col['nombre'], $col['obligatoria'] ? $estOblig : $estCab);
            }

            return new Row($celdas);
        };

        $writer = new XlsxWriter();
        $writer->openToFile($destino);

        // ---- 1) Datos -----------------------------------------------------
        $hoja = $writer->getCurrentSheet();
        $hoja->setName('Datos');
        $this->anchos($hoja, $cabeceras);
        $writer->addRow($filaCabecera());

        // ---- 2) Ejemplo ---------------------------------------------------
        $hoja = $writer->addNewSheetAndMakeItCurrent();
        $hoja->setName('Ejemplo');
        $this->anchos($hoja, $cabeceras);
        $writer->addRow($filaCabecera());
        for ($e = 0; $e < 2; $e++) {
            $celdas = [];
            foreach ($def['columnas'] as $k => $col) {
                $v = $col['ejemplos'][$e] ?? null;
                if ($v !== null && $esTexto[$k]) {
                    $v = (string) $v;
                }
                $celdas[] = Cell::fromValue($v, $esTexto[$k] ? $estTexto : null);
            }
            $writer->addRow(new Row($celdas));
        }
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['EJEMPLO: estas 2 filas son ilustrativas y NO se cargan. Copie el formato en la hoja "Datos" (la primera hoja), que es la única que lee el sistema.'], $estAviso));

        // ---- 3) Instrucciones ---------------------------------------------
        $hoja = $writer->addNewSheetAndMakeItCurrent();
        $hoja->setName('Instrucciones');
        $hoja->setColumnWidth(28, 1);
        $hoja->setColumnWidth(14, 2);
        $hoja->setColumnWidth(60, 3);
        $hoja->setColumnWidth(55, 4);
        $hoja->setColumnWidth(40, 5);

        $writer->addRow(Row::fromValues(['GEODATA · Plantilla de carga: '.$def['titulo']], $estTitulo));
        $writer->addRow(Row::fromValues(['Unidad: '.$def['unidad']], $estNegrita));
        $writer->addRow(Row::fromValues(['Generada: '.now()->format('d/m/Y H:i').' (America/La_Paz)']));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Cómo usarla'], $estNegrita));
        foreach ($def['notas'] as $i => $nota) {
            $writer->addRow(Row::fromValues([($i + 1).'.', $nota]));
        }
        $writer->addRow(Row::fromValues(['•', 'No cambie el nombre de las cabeceras de la hoja "Datos" ni agregue filas de título encima: la primera fila debe ser la cabecera.']));
        $writer->addRow(Row::fromValues(['•', 'Números sin separador de miles; use punto o coma decimal (no ambos). Celdas vacías = sin dato.']));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(new Row([
            Cell::fromValue('Columna', $estCab), Cell::fromValue('¿Obligatoria?', $estCab),
            Cell::fromValue('Significado', $estCab), Cell::fromValue('Formato / unidad', $estCab),
            Cell::fromValue('También se acepta como', $estCab),
        ]));
        foreach ($def['columnas'] as $col) {
            $writer->addRow(new Row([
                Cell::fromValue($col['nombre'], $estNegrita),
                Cell::fromValue($col['obligatoria'] ? 'Sí' : 'No'),
                Cell::fromValue($col['descripcion'], $estAjuste),
                Cell::fromValue($col['formato'], $estAjuste),
                Cell::fromValue(implode(', ', $col['sinonimos'] ?? []), $estAjuste),
            ]));
        }

        $writer->close();

        return $def;
    }

    private function anchos($hoja, array $cabeceras): void
    {
        foreach ($cabeceras as $i => $c) {
            $hoja->setColumnWidth(max(12, min(40, mb_strlen($c) + 6)), $i + 1);
        }
    }
}
