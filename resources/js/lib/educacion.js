/**
 * Panel Educación: nivel de dificultad (mejora 6), glosario emergente (mejora 3)
 * y utilidades compartidas. El glosario es contenido ESTÁTICO servido por
 * /portal/v2/aprende/glosario (con ejemplos calculados en vivo en el servidor):
 * no hay ningún asistente de IA.
 */
import { ref, watch } from 'vue';

const CLAVE_NIVEL = 'gd.nivel';
export const NIVELES = [
    { v: 'colegio', t: 'Colegio' },
    { v: 'universidad', t: 'Universidad' },
];

function nivelInicial() {
    if (typeof window === 'undefined') return 'colegio';
    try {
        const q = new URLSearchParams(window.location.search).get('nivel');
        if (q === 'colegio' || q === 'universidad') {
            window.localStorage.setItem(CLAVE_NIVEL, q);
            return q;
        }
        return window.localStorage.getItem(CLAVE_NIVEL) || 'colegio';
    } catch {
        return 'colegio';
    }
}

/** Nivel elegido (persistente en localStorage; ?nivel=colegio|universidad lo fija). */
export const nivel = ref(nivelInicial());
watch(nivel, (v) => {
    try { window.localStorage.setItem(CLAVE_NIVEL, v); } catch { /* sin almacenamiento */ }
});

/** Elige la versión del texto según el nivel ({colegio, universidad} o string). */
export function porNivel(obj, n = nivel.value) {
    if (obj === null || obj === undefined) return '';
    if (typeof obj === 'string' || Array.isArray(obj)) return obj;
    return obj[n] ?? obj.colegio ?? '';
}

// ---------------- Glosario ----------------
export const glosario = ref(null);
let promesa = null;
export function cargarGlosario() {
    if (!promesa) {
        promesa = window.axios.get('/portal/v2/aprende/glosario')
            .then(({ data }) => { glosario.value = data; return data; })
            .catch((e) => { promesa = null; throw e; });
    }
    return promesa;
}

/** Parte un texto con marcas [[clave|texto]] en segmentos {t} o {clave, t}. */
export function segmentos(texto) {
    const out = [];
    const s = String(texto ?? '');
    const re = /\[\[([a-z0-9-]+)(?:\|([^\]]+))?\]\]/g;
    let i = 0;
    let m;
    while ((m = re.exec(s))) {
        if (m.index > i) out.push({ t: s.slice(i, m.index) });
        out.push({ clave: m[1], t: m[2] || m[1] });
        i = re.lastIndex;
    }
    if (i < s.length) out.push({ t: s.slice(i) });
    return out;
}

/** Texto plano (sin marcas de glosario), para títulos o atributos. */
export function textoPlano(texto) {
    return segmentos(texto).map((x) => x.t).join('');
}

export async function postJson(url, datos = {}) {
    const { data } = await window.axios.post(url, datos);
    return data;
}
