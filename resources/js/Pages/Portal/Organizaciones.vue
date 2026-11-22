<script setup>
/**
 * Organizaciones y bases de datos (estilo mockup, GEO-12).
 * Las tarjetas muestran lo que realmente hay cargado de cada fuente
 * (filas, años, archivos); las que no tienen datos dicen "sin datos".
 */
import { Head, Link } from '@inertiajs/vue3';
import GdCobertura from '@/Components/Gd/GdCobertura.vue';
import { fmtNum } from '@/lib/gd';

const props = defineProps({
    fuentes: { type: Array, default: () => [] },
    cobertura: { type: Object, default: null },
});

const INFO = {
    INE: { titulo: 'INE Bolivia', img: '/img/portal/org-ine.webp', bg: 'linear-gradient(160deg,#5e1224,#12203d)',
           desc: 'Instituto Nacional de Estadística: microdatos de exportación e importación por subpartida NANDINA, país, departamento, aduana y medio de transporte.' },
    FAOSTAT: { titulo: 'FAOSTAT ONU', img: '/img/portal/org-faostat.webp', bg: 'linear-gradient(160deg,#14532d,#12203d)',
           desc: 'Organización de las Naciones Unidas para la Alimentación y la Agricultura: producción, comercio agropecuario e indicadores agrícolas de Bolivia.' },
    ALADI: { titulo: 'ALADI Regional', img: '/img/portal/org-aladi.webp', bg: 'linear-gradient(160deg,#1e3a8a,#12203d)',
           desc: 'Asociación Latinoamericana de Integración: rankings de comercio intrarregional por país miembro y producto.' },
    MERCOSUR: { titulo: 'MERCOSUR', img: '/img/portal/org-mercosur.webp', bg: 'linear-gradient(160deg,#0f766e,#12203d)',
           desc: 'Mercado Común del Sur: series de comercio por zona y producto con Brasil, Argentina, Paraguay y Uruguay.' },
};
const info = (s) => INFO[s] ?? { titulo: s, img: '', bg: 'linear-gradient(160deg,#334155,#12203d)', desc: '' };
const fecha = (f) => (f ? new Date(f).toLocaleDateString('es-BO', { day: '2-digit', month: 'short', year: 'numeric' }) : '—');
function regs(n) {
    if (!n) return 'Sin datos';
    if (n >= 1e6) return `${fmtNum(n / 1e6, 2)} M reg.`;
    if (n >= 1e3) return `${fmtNum(n / 1e3, 1)} mil reg.`;
    return `${fmtNum(n)} reg.`;
}
</script>

<template>
    <Head title="Organizaciones" />
    <div class="page-wrap gd-pt">
        <div style="margin-bottom:24px">
            <span class="gd-kicker">● Repositorio multifuente GEODATA</span>
            <h2 class="gd-titulo">Organizaciones y Bases de Datos</h2>
            <p class="gd-sub">Las 4 instituciones fuente del sistema. Cada tarjeta muestra lo que hoy está cargado en la base de datos; haz clic para ver sus indicadores, gráficos y descargas.</p>
        </div>

        <div class="org-retreat-grid">
            <article v-for="f in fuentes" :key="f.organizacion_id" class="retreat-card"
                     :style="{ backgroundImage: `url(${info(f.sigla).img}), ${info(f.sigla).bg}` }">
                <div class="rc-content">
                    <div class="rc-head"><h3>{{ info(f.sigla).titulo }}</h3><span class="rc-price-pill">{{ regs(f.filas) }}</span></div>
                    <p class="rc-desc">{{ info(f.sigla).desc }}</p>
                    <div class="rc-tags">
                        <span v-if="f.desde" class="rc-tag">Cobertura {{ f.desde }}–{{ f.hasta }}</span>
                        <span v-if="f.archivos" class="rc-tag">{{ fmtNum(f.archivos) }} archivos cargados</span>
                        <span v-if="!f.filas" class="rc-tag" style="background:#fef3c7;color:#92400e">Sin datos cargados</span>
                    </div>
                    <Link class="rc-reserve-btn" :href="`/organizaciones/${f.organizacion_id}`">Explorar gráficos y bases</Link>
                </div>
            </article>
        </div>

        <div class="overview-panel">
            <div class="bento-h"><h3>Estado de carga por fuente</h3><span class="badge-var">Datos en vivo de la base</span></div>
            <div style="overflow-x:auto">
                <table class="data-table">
                    <thead><tr><th>Fuente</th><th>Institución</th><th class="gd-num">Registros</th><th>Gestiones</th><th class="gd-num">Archivos</th><th>Última carga</th><th></th></tr></thead>
                    <tbody>
                        <tr v-for="f in fuentes" :key="f.organizacion_id">
                            <td><b>{{ f.sigla }}</b></td>
                            <td>{{ f.nombre }}</td>
                            <td class="gd-num">{{ f.filas ? fmtNum(f.filas) : '—' }}</td>
                            <td>
                                <template v-if="f.cobertura">Exp. {{ f.cobertura.exp.etiqueta }} · Imp. {{ f.cobertura.imp.etiqueta }}</template>
                                <template v-else-if="f.desde">{{ f.desde }}–{{ f.hasta }}</template>
                                <span v-else class="gd-tag-sindatos">Sin datos</span>
                            </td>
                            <td class="gd-num">{{ f.archivos || '—' }}</td>
                            <td>{{ fecha(f.ultima) }}</td>
                            <td class="gd-nowrap"><Link class="tool-btn" :href="`/organizaciones/${f.organizacion_id}`">Ver →</Link></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div style="margin-top:12px"><GdCobertura :cobertura="cobertura" /></div>
            <p class="gd-nota">INE, ALADI, MERCOSUR y FAOSTAT tienen coberturas distintas (microdato nacional, rankings regionales, bloque y agro): sus cifras no se suman entre sí.</p>
        </div>
    </div>
</template>
