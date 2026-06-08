<?php

namespace App\Servicios;

use Barryvdh\DomPDF\Facade\Pdf;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exporta un reporte (titulo + columnas + filas + resumen) a XLSX, CSV o PDF.
 */
class ExportadorReporte
{
    /**
     * @param array $reporte  ['titulo','columnas','filas','resumen']
     */
    public function descargar(array $reporte, string $formato): mixed
    {
        $nombre = $this->nombreArchivo($reporte['titulo']);

        return match ($formato) {
            'xlsx' => $this->planilla($reporte, $nombre.'.xlsx', new XlsxWriter()),
            'csv'  => $this->planilla($reporte, $nombre.'.csv', new CsvWriter()),
            'pdf'  => $this->pdf($reporte, $nombre.'.pdf'),
            default => abort(400, 'Formato no soportado.'),
        };
    }

    private function nombreArchivo(string $titulo): string
    {
        $base = preg_replace('/[^a-z0-9]+/i', '_', strtolower($titulo));

        return trim($base, '_').'_'.now()->format('Ymd_His');
    }

    /**
     * XLSX/CSV en streaming (bajo consumo de memoria).
     */
    private function planilla(array $reporte, string $archivo, $writer): StreamedResponse
    {
        return new StreamedResponse(function () use ($reporte, $writer) {
            $writer->openToFile('php://output');
            // Cabecera de columnas.
            $writer->addRow(Row::fromValues($reporte['columnas']));
            // Filas.
            foreach ($reporte['filas'] as $fila) {
                $writer->addRow(Row::fromValues($fila));
            }
            // Linea de resumen.
            $writer->addRow(Row::fromValues([]));
            foreach ($reporte['resumen'] as $k => $v) {
                $writer->addRow(Row::fromValues([$k, $v]));
            }
            $writer->close();
        }, 200, [
            'Content-Type'        => str_ends_with($archivo, '.xlsx')
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$archivo.'"',
        ]);
    }

    private function pdf(array $reporte, string $archivo): mixed
    {
        $pdf = Pdf::loadView('reportes.pdf', ['reporte' => $reporte])
            ->setPaper('a4', 'landscape');

        return $pdf->download($archivo);
    }
}
