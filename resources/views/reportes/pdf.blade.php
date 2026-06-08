<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #1e293b; }
        h1 { font-size: 16px; color: #1e40af; margin: 0 0 2px; }
        .sub { color: #64748b; font-size: 9px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1e40af; color: #fff; text-align: left; padding: 5px 6px; font-size: 9px; }
        td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; }
        tr:nth-child(even) td { background: #f8fafc; }
        .num { text-align: right; }
        .resumen { margin-top: 12px; }
        .resumen td { border: none; padding: 2px 6px; }
        .resumen .k { color: #64748b; }
        .resumen .v { font-weight: bold; text-align: right; }
    </style>
</head>
<body>
    <h1>{{ $reporte['titulo'] }}</h1>
    <div class="sub">ComexHub · Generado el {{ now()->format('d/m/Y H:i') }}</div>

    <table>
        <thead>
            <tr>
                @foreach ($reporte['columnas'] as $i => $col)
                    <th class="{{ $i >= 1 && $loop->last || $i >= 2 ? 'num' : '' }}">{{ $col }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($reporte['filas'] as $fila)
                <tr>
                    @foreach ($fila as $i => $celda)
                        <td class="{{ is_numeric($celda) && $i > 0 ? 'num' : '' }}">
                            {{ is_numeric($celda) && $i > 0 ? number_format($celda, $celda == (int)$celda ? 0 : 2, ',', '.') : $celda }}
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="resumen">
        @foreach ($reporte['resumen'] as $k => $v)
            <tr>
                <td class="k">{{ $k }}</td>
                <td class="v">{{ is_numeric($v) ? number_format($v, $v == (int)$v ? 0 : 2, ',', '.') : $v }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
