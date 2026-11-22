# Carga local de microdatos INE (22/11/2026)

Respaldo previo de la BD: `C:\Users\hp\Documents\GEODATA_backup\brisa_pre_ine.sql`. Archivos descargados del Drive en `C:\Users\hp\Documents\GEODATA_datos\INE\{exportaciones,importaciones}`: 70 .xlsx, 1,57 GB.

## Cambios en `app/Servicios/CargadorIne.php`

Respaldo del archivo anterior: `GEODATA_backup\CargadorIne.pre_sinonimos.php`.

1. **Cabeceras largas (sinónimos).** Los Excel de exportaciones del Drive (1992–2026) traen cabeceras descriptivas largas, por ejemplo «GESTIÓN DE REFERENCIA EN LA QUE…», «CODIGO ARANCELARIO NANDINA (…)» o «VALOR FOB (dólares)», en vez de GESTION, NANDINA o VALOR. Por eso se rechazaban con «No se encontró la fila de cabecera». La constante `CABECERAS_LARGAS` y el método `canonCabecera()` las traducen al nombre corto antes de buscar la cabecera; las cabeceras cortas no cambian.
2. **Valores negativos.** Las bases de importación traen algunos valores negativos, sobre todo PAG (gravámenes: devoluciones o ajustes), y la tabla exige `>= 0`. Antes, cada fila negativa hacía fallar su lote de 2000 filas. El lote se reintentaba fila por fila, con un SAVEPOINT por fila, y en archivos grandes eso agotaba la tabla de bloqueos de PostgreSQL («memoria compartida agotada», `max_locks_per_transaction`). La carga entera se revertía con 0 filas. Probablemente esta es la causa de los FALLIDO de importaciones 1993–2016 y 2024 en producción.
   - Ahora el valor original se guarda en `atributos_extra.negativos`, la columna queda NULL y se deja una advertencia en Calidad: un resumen por carga y las primeras 20 filas.
   - Probado con Importaciones 2000: 166.645 filas, de las cuales 19 tenían negativos.
