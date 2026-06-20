/**
 * Colores de marca por organización (espejo de organizacion_detalle.color_primario).
 * Úsalo cuando el dato no traiga color propio desde la API.
 */
export const COLORES_ORG = {
    1: '#102A45', // INE
    2: '#2E7D32', // ALADI
    3: '#1A73B7', // MERCOSUR
    4: '#C77D11', // FAOSTAT
};

export const COLORES_ORG_SIGLA = {
    INE: '#102A45',
    ALADI: '#2E7D32',
    MERCOSUR: '#1A73B7',
    FAOSTAT: '#C77D11',
};

/** Paleta Ovxel para series de ApexCharts (orden de aparición). */
export const PALETA_OVXEL = [
    '#102A45',
    '#E31219',
    '#2E7D32',
    '#1A73B7',
    '#C77D11',
    '#5B6B7D',
    '#8B9AAC',
];

export function colorOrg(id, fallback = '#102A45') {
    return COLORES_ORG[id] ?? COLORES_ORG_SIGLA[id] ?? fallback;
}

/** Logo oficial por organización (archivos en public/img/organizaciones). */
export const LOGOS_ORG = {
    1: '/img/organizaciones/Logo-INE-Bolivia.webp', // INE
    2: '/img/organizaciones/ALADI.webp',            // ALADI
    3: '/img/organizaciones/Mercosur.webp',         // MERCOSUR
    4: '/img/organizaciones/Fao.webp',              // FAOSTAT
};

export const LOGOS_ORG_SIGLA = {
    INE: '/img/organizaciones/Logo-INE-Bolivia.webp',
    ALADI: '/img/organizaciones/ALADI.webp',
    MERCOSUR: '/img/organizaciones/Mercosur.webp',
    FAOSTAT: '/img/organizaciones/Fao.webp',
};

/** Devuelve la ruta del logo por id o sigla (null si no hay). */
export function logoOrg(idOSigla) {
    return LOGOS_ORG[idOSigla] ?? LOGOS_ORG_SIGLA[idOSigla] ?? null;
}
