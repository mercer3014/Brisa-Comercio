/**
 * Helpers del portal rediseñado (estilo mockup GEODATA, GEO-12).
 * Formatos como en el diseño: "$1.390 M", "−$534 M", "92,6%".
 */

function agrupar(entero) {
    return String(entero).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function num(v, dec = 0) {
    const n = Math.abs(Number(v));
    const fijo = n.toFixed(dec);
    const [e, d] = fijo.split('.');
    return agrupar(e) + (d ? ',' + d : '');
}

/** Millones de USD estilo mockup. null => "—" (sin datos, nunca 0). */
export function fmtM(v, conSigno = false) {
    if (v === null || v === undefined || isNaN(v)) return '—';
    const m = Number(v) / 1e6;
    const abs = Math.abs(m);
    const dec = abs < 10 ? 1 : 0;
    const signo = m < 0 ? '−' : (conSigno && m > 0 ? '+' : '');
    return `${signo}$${num(abs, dec)} M`;
}

/** Valor en miles de millones si es muy grande (ejes y textos cortos). */
export function fmtMM(v) {
    if (v === null || v === undefined || isNaN(v)) return '—';
    const n = Number(v);
    if (Math.abs(n) >= 1e9) return `${n < 0 ? '−' : ''}$${num(Math.abs(n) / 1e9, 2)} MM`;
    return fmtM(n);
}

export function fmtNum(v, dec = 0) {
    if (v === null || v === undefined || isNaN(v)) return '—';
    return (Number(v) < 0 ? '−' : '') + num(v, dec);
}

export function fmtPct(v, conSigno = false, dec = 1) {
    if (v === null || v === undefined || isNaN(v)) return '—';
    const s = Number(v) < 0 ? '−' : (conSigno && Number(v) > 0 ? '+' : '');
    return `${s}${num(Math.abs(v), dec)}%`;
}

export function fmtUsd(v, dec = 0) {
    if (v === null || v === undefined || isNaN(v)) return '—';
    return `${Number(v) < 0 ? '−' : ''}$${num(Math.abs(v), dec)}`;
}

/** Nombres cortos de las 21 secciones del Sistema Armonizado. */
export const SECCIONES_SA = {
    0: 'Sin sección',
    1: 'Animales vivos y productos del reino animal',
    2: 'Productos del reino vegetal',
    3: 'Grasas y aceites',
    4: 'Alimentos, bebidas y tabaco',
    5: 'Productos minerales',
    6: 'Productos químicos',
    7: 'Plástico y caucho',
    8: 'Pieles y cueros',
    9: 'Madera y corcho',
    10: 'Papel y cartón',
    11: 'Textiles',
    12: 'Calzado y sombreros',
    13: 'Piedra, cerámica y vidrio',
    14: 'Metales preciosos y joyería',
    15: 'Metales comunes',
    16: 'Máquinas y aparatos eléctricos',
    17: 'Material de transporte',
    18: 'Instrumentos de precisión',
    19: 'Armas y municiones',
    20: 'Manufacturas diversas',
    21: 'Objetos de arte y antigüedades',
};

export const COLORES_SECCION = [
    '#5e1224', '#3b7a8c', '#f59e0b', '#10b981', '#7c5dfa', '#f9845b', '#0ea5e9', '#c81e3a',
    '#12203d', '#f6c844', '#64748b', '#4f7785', '#b6cad2', '#9d4edd', '#2a9d8f', '#e76f51',
    '#8ab17d', '#e9c46a', '#264653', '#bc6c25', '#606c38', '#94a3b8',
];

export function colorSeccion(sec) {
    return COLORES_SECCION[(Number(sec) || 0) % COLORES_SECCION.length];
}

/** Bandera emoji a partir del ISO-2 (o null). */
export function bandera(iso2) {
    if (!iso2 || iso2.length !== 2) return '';
    return String.fromCodePoint(...[...iso2.toUpperCase()].map((c) => 127397 + c.charCodeAt(0)));
}

/** Etiqueta de gestión: "2026p" si el año está incompleto. */
export function etiquetaGestion(g, cobertura, flujo = 'exp') {
    const meses = cobertura?.[flujo]?.meses?.[g];
    return meses && meses < 12 ? `${g}p` : `${g}`;
}

/** Descarga un CSV (Excel lo abre directo) con BOM UTF-8 y ';' como separador. */
export function descargarCsv(nombre, filas) {
    const esc = (v) => {
        if (v === null || v === undefined) return '';
        const s = String(v);
        return /[;"\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
    };
    const txt = filas.map((f) => f.map(esc).join(';')).join('\r\n');
    const blob = new Blob(['\ufeff' + txt], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = nombre;
    a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
}

/** GET JSON con axios (ya configurado en bootstrap.js). */
export async function getJson(url, params = {}) {
    const { data } = await window.axios.get(url, { params });
    return data;
}
