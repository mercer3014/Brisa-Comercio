<script setup>
/** Historias con datos / recorridos guiados (mejora 5). */
import { Head, Link } from '@inertiajs/vue3';
import GdNivel from '@/Components/Gd/GdNivel.vue';
import GdCobertura from '@/Components/Gd/GdCobertura.vue';

defineProps({
    historias: { type: Array, default: () => [] },
    cobertura: { type: Object, default: null },
    participante: { type: Object, default: null },
});
const COLORES = ['#5e1224', '#10b981', '#3b82f6', '#f59e0b', '#12203d'];
</script>

<template>
    <Head title="Historias con datos" />
    <div class="page-wrap gd-pt">
        <div class="gd-cab">
            <div>
                <span class="gd-kicker">● Panel Educación · Historias con datos</span>
                <h2 class="gd-titulo">Recorridos guiados por el comercio de Bolivia</h2>
                <p class="gd-sub">Cada historia avanza paso a paso: un texto corto, un gráfico con las cifras reales del INE y un enlace para seguir explorando en el portal. Sirve para clase o para leer en casa.</p>
            </div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <GdNivel />
                <Link class="tool-btn" href="/aprende">← Panel Educación</Link>
            </div>
        </div>
        <div class="edu-modulos" style="grid-template-columns:repeat(auto-fill,minmax(260px,1fr))">
            <component :is="h.disponible ? Link : 'div'" v-for="(h, i) in historias" :key="h.slug" :href="h.disponible ? `/historias/${h.slug}` : undefined"
                       class="edu-modulo" :style="`--c:${COLORES[i % COLORES.length]};${h.disponible ? '' : 'opacity:.7'}`">
                <span class="n">{{ h.pasos }} pasos</span>
                <h3>{{ h.titulo }}</h3>
                <p>{{ h.resumen }}</p>
                <span v-if="h.disponible" class="ir">Empezar el recorrido →</span>
                <span v-else class="gd-tag-sindatos" style="align-self:flex-start">Sin datos cargados</span>
            </component>
        </div>
        <div class="overview-panel" style="margin-top:22px">
            <div class="bento-h"><h3>Sobre los datos</h3><GdCobertura :cobertura="cobertura" /></div>
            <p style="font-size:0.86rem;color:var(--muted)">Las cifras de cada paso se calculan al abrir la historia. Una historia aparece como “Sin datos cargados” cuando su fuente aún no está en la base (por ejemplo, la serie MERCOSUR).</p>
        </div>
    </div>
</template>
