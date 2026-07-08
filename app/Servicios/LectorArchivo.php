<?php

namespace App\Servicios;

use Generator;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Common\Entity\Row;

/**
 * Lectura eficiente (streaming) de archivos XLSX/CSV. No carga el archivo
 * completo en memoria: itera fila por fila. Apto para ~400.000 filas.
 */
class LectorArchivo
{
    /**
     * Crea el lector según la extension.
     */
    private function crearLector(string $extension)
    {
        $ext = strtolower($extension);

        if ($ext === 'csv') {
            $opciones = new CsvOptions();
            $opciones->FIELD_DELIMITER = $this->detectarDelimitador();

            return new CsvReader($opciones);
        }

        // xlsx (y xlsm)
        return new XlsxReader();
    }

    private string $delimitador = ',';

    private function detectarDelimitador(): string
    {
        return $this->delimitador;
    }

    /**
     * Permite fijar el delimitador del CSV antes de leer (por defecto ',').
     */
    public function conDelimitador(string $delim): static
    {
        $this->delimitador = $delim;

        return $this;
    }

    /**
     * Lee la fila de cabeceras y hasta $n filas de muestra.
     *
     * @return array{cabeceras: string[], muestra: array<int, array<int, mixed>>}
     */
    public function leerCabecerasYMuestra(string $ruta, string $extension, int $n = 20): array
    {
        if (strtolower($extension) === 'xls') {
            $cabeceras = [];
            $muestra = [];
            foreach ($this->iterarXls($ruta) as $i => $asoc) {
                if (empty($cabeceras)) {
                    $cabeceras = array_keys($asoc);
                }
                $muestra[] = array_values($asoc);
                if (count($muestra) >= $n) {
                    break;
                }
            }

            return ['cabeceras' => $cabeceras, 'muestra' => $muestra];
        }

        $lector = $this->crearLector($extension);
        $lector->open($ruta);

        $cabeceras = [];
        $muestra = [];

        foreach ($lector->getSheetIterator() as $hoja) {
            $i = 0;
            foreach ($hoja->getRowIterator() as $fila) {
                $valores = $this->filaAArreglo($fila);

                if ($i === 0) {
                    $cabeceras = array_map(fn ($v) => trim((string) $v), $valores);
                } else {
                    $muestra[] = $valores;
                    if (count($muestra) >= $n) {
                        break;
                    }
                }
                $i++;
            }
            break; // solo la primera hoja
        }

        $lector->close();

        return ['cabeceras' => $cabeceras, 'muestra' => $muestra];
    }

    /**
     * Itera todas las filas de datos (sin la cabecera) como arreglos asociativos
     * cabecera => valor. Generator: bajo consumo de memoria.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function iterarAsociativo(string $ruta, string $extension): Generator
    {
        if (strtolower($extension) === 'xls') {
            yield from $this->iterarXls($ruta);

            return;
        }

        $lector = $this->crearLector($extension);
        $lector->open($ruta);

        foreach ($lector->getSheetIterator() as $hoja) {
            $cabeceras = null;
            $numero = 0;

            foreach ($hoja->getRowIterator() as $fila) {
                $valores = $this->filaAArreglo($fila);

                if ($cabeceras === null) {
                    $cabeceras = array_map(fn ($v) => trim((string) $v), $valores);
                    continue;
                }

                $numero++;
                $asoc = [];
                foreach ($cabeceras as $idx => $nombre) {
                    $asoc[$nombre] = $valores[$idx] ?? null;
                }

                yield $numero => $asoc;
            }
            break; // solo la primera hoja
        }

        $lector->close();
    }

    /**
     * Itera un .xls binario (Excel 97-2003) vía PhpSpreadsheet, por bloques
     * para no armar un arreglo gigante. OpenSpout solo soporta xlsx/csv.
     *
     * @return Generator<int, array<string, mixed>>
     */
    private function iterarXls(string $ruta): Generator
    {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($ruta);
        $reader->setReadDataOnly(true);
        $libro = $reader->load($ruta);
        $hoja  = $libro->getSheet(0);

        $maxFila = $hoja->getHighestRow();
        $colMax  = $hoja->getHighestColumn();
        $maxCol  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($colMax);

        $cabeceras = [];
        for ($c = 1; $c <= $maxCol; $c++) {
            $cabeceras[] = trim((string) $hoja->getCell([$c, 1])->getValue());
        }

        $numero = 0;
        for ($desde = 2; $desde <= $maxFila; $desde += 5000) {
            $hasta = min($desde + 4999, $maxFila);
            $bloque = $hoja->rangeToArray("A{$desde}:{$colMax}{$hasta}", null, false, false, false);
            foreach ($bloque as $valores) {
                $numero++;
                $asoc = [];
                foreach ($cabeceras as $i => $nombre) {
                    $asoc[$nombre] = $valores[$i] ?? null;
                }
                yield $numero => $asoc;
            }
        }

        $libro->disconnectWorksheets();
    }

    /**
     * Convierte una fila de OpenSpout a arreglo de valores escalares.
     */
    private function filaAArreglo(Row $fila): array
    {
        $celdas = $fila->getCells();

        return array_map(function ($celda) {
            $valor = $celda->getValue();

            if ($valor instanceof \DateTimeInterface) {
                return $valor->format('Y-m-d');
            }

            return $valor;
        }, $celdas);
    }
}
