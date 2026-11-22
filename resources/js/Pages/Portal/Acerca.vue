<script setup>
/**
 * Acerca de (estilo mockup, GEO-12). Los números de la portada institucional
 * (registros, fuentes con datos, cobertura) salen de la base, no del diseño.
 */
import { Head, Link } from '@inertiajs/vue3';
import GdCobertura from '@/Components/Gd/GdCobertura.vue';
import { fmtNum } from '@/lib/gd';

defineProps({
    cobertura: { type: Object, default: null },
    registros: { type: Number, default: 0 },
    fuentesConDatos: { type: Number, default: 0 },
});
</script>

<template>
    <Head title="Acerca de" />
    <div class="page-wrap gd-pt">
        <div class="gd-acerca-banner">
            <div>
                <span class="gd-acerca-pill">IIESJOM · LABORATORIO LICI · UAGRM - FCEE</span>
                <h2>GEODATA: Un futuro de datos abiertos</h2>
                <p>El proyecto <b>GEODATA</b> fue desarrollado por el equipo de Base de Datos del <b>IIESJOM</b>, con el apoyo del
                    <b>LICI</b> de la <b>FCEE - UAGRM</b>, reuniendo múltiples fuentes oficiales en un único portal interactivo de acceso libre y gratuito.</p>
                <div style="display:flex;gap:12px;flex-wrap:wrap">
                    <span class="rc-tag" style="background:#10b981;color:#fff">✓ Acceso 100% gratuito</span>
                    <span class="rc-tag">✓ {{ registros >= 1e6 ? fmtNum(registros / 1e6, 2) + ' millones' : fmtNum(registros) }} de registros cargados</span>
                    <span class="rc-tag">✓ {{ fuentesConDatos }} de 4 fuentes con datos</span>
                </div>
            </div>
            <div class="gd-acerca-img" style="background-image:url(/img/portal/acerca-equipo.webp)"></div>
        </div>

        <div class="grid-3">
            <div class="bento-box" style="border-top:5px solid var(--crimson)">
                <div class="gd-acerca-foto" style="background-image:url(/img/portal/acerca-etapa1.webp)"></div>
                <span style="font-size:0.72rem;font-weight:800;color:var(--crimson)">ETAPA 01 · RECOLECCIÓN DE DATOS</span>
                <h3 style="font-size:1.12rem;color:var(--navy);margin:4px 0 6px">Centralización de 4 fuentes</h3>
                <p style="font-size:0.83rem;color:var(--muted)">Carga de las bases oficiales del <b>INE, FAOSTAT, ALADI y MERCOSUR</b> en una sola base PostgreSQL, con plantillas Excel por fuente y flujo.</p>
            </div>
            <div class="bento-box" style="border-top:5px solid var(--imp)">
                <div class="gd-acerca-foto" style="background-image:url(/img/portal/acerca-etapa2.webp)"></div>
                <span style="font-size:0.72rem;font-weight:800;color:var(--imp)">ETAPA 02 · ARMONIZACIÓN ETL</span>
                <h3 style="font-size:1.12rem;color:var(--navy);margin:4px 0 6px">Limpieza y estandarización</h3>
                <p style="font-size:0.83rem;color:var(--muted)">Subpartidas NANDINA a 10 dígitos, países homologados a ISO 3166 y M49, y una sola regla de valor: FOB para exportaciones y CIF frontera para importaciones.</p>
            </div>
            <div class="bento-box" style="border-top:5px solid var(--exp)">
                <div class="gd-acerca-foto" style="background-image:url(/img/portal/acerca-etapa3.webp)"></div>
                <span style="font-size:0.72rem;font-weight:800;color:var(--exp)">ETAPA 03 · COMUNIDAD UAGRM</span>
                <h3 style="font-size:1.12rem;color:var(--navy);margin:4px 0 6px">Investigación sin barreras</h3>
                <p style="font-size:0.83rem;color:var(--muted)">Diseñado por estudiantes de Comercio Internacional e ingenieros de sistemas para tesis, monografías y toma de decisiones.</p>
            </div>
        </div>

        <div class="overview-panel" style="margin-top:24px">
            <div class="bento-h"><h3>Metodología y alcance de los datos</h3><Link class="tool-btn" href="/organizaciones">Ver fuentes →</Link></div>
            <div class="grid-2" style="gap:18px">
                <div style="font-size:0.86rem;color:#334155;display:grid;gap:8px">
                    <p><b>Valores.</b> Exportaciones a valor FOB e importaciones a valor CIF frontera, en dólares corrientes, tal como los publica el INE.</p>
                    <p><b>Gestiones preliminares.</b> El último año del INE suele estar incompleto (marcado con “p”); el portal compara siempre el mismo periodo del año anterior.</p>
                    <p><b>Sin datos no es cero.</b> Cuando un flujo o una fuente no están cargados, el portal lo indica como “sin datos” en vez de mostrar cifras de ejemplo.</p>
                </div>
                <div style="font-size:0.86rem;color:#334155;display:grid;gap:8px">
                    <p><b>Coberturas distintas.</b> INE es el microdato nacional; ALADI publica rankings regionales; MERCOSUR, el comercio del bloque, y FAOSTAT, el sector agropecuario. No se suman entre sí.</p>
                    <p><b>Países.</b> Cada país de cada fuente se vincula a su código ISO (alfa-2, alfa-3 y M49), para cruzar fuentes y dibujar mapas.</p>
                    <GdCobertura :cobertura="cobertura" />
                </div>
            </div>
        </div>
    </div>
</template>
