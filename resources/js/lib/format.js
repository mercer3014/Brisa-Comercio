/**
 * Helpers de formato compartidos por el portal. Centralizan el locale es-BO
 * para que todos los números/monedas se vean igual en todas las páginas.
 */

/** Número entero con separador de miles. */
export function fmtNum(v, decimales = 0) {
    if (v == null || isNaN(v)) return '—';
    return Number(v).toLocaleString('es-BO', { minimumFractionDigits: decimales, maximumFractionDigits: decimales });
}

/** Valor monetario en USD (formato largo). */
export function fmtUsd(v, decimales = 0) {
    if (v == null || isNaN(v)) return '—';
    return 'USD ' + Number(v).toLocaleString('es-BO', { maximumFractionDigits: decimales });
}

/** Valor compacto: 1.5M, 2.8B, 950K. Útil para ejes y KPIs grandes. */
export function fmtCompacto(v) {
    if (v == null || isNaN(v)) return '—';
    const n = Number(v);
    const abs = Math.abs(n);
    if (abs >= 1e9) return (n / 1e9).toLocaleString('es-BO', { maximumFractionDigits: 1 }) + ' B';
    if (abs >= 1e6) return (n / 1e6).toLocaleString('es-BO', { maximumFractionDigits: 1 }) + ' M';
    if (abs >= 1e3) return (n / 1e3).toLocaleString('es-BO', { maximumFractionDigits: 1 }) + ' K';
    return n.toLocaleString('es-BO', { maximumFractionDigits: 0 });
}

/** Porcentaje con signo opcional. */
export function fmtPct(v, conSigno = false, decimales = 1) {
    if (v == null || isNaN(v)) return '—';
    const s = conSigno && v > 0 ? '+' : '';
    return `${s}${Number(v).toLocaleString('es-BO', { maximumFractionDigits: decimales })}%`;
}

/** Formateador para ejes de ApexCharts (valores compactos). */
export const ejeCompacto = (v) => fmtCompacto(v);
