<script setup>
/** Torta tipo anillo del mockup (SVG propio), con N segmentos y leyenda. */
import { computed } from 'vue';
import { fmtM, fmtPct } from '@/lib/gd';

const props = defineProps({
    partes: { type: Array, default: () => [] }, // [{nombre, valor, pct}]
    colores: { type: Array, default: () => ['#4f7785', '#b6cad2', '#e2e8f0', '#94a3b8'] },
});

const r = 64;
const c = 2 * Math.PI * r;

const segmentos = computed(() => {
    let acum = 0;
    return props.partes.map((p, i) => {
        const len = (p.pct / 100) * c;
        const ini = acum;
        acum += len;
        const angMedio = ((ini + len / 2) / c) * 360 - 90;
        const rad = (angMedio * Math.PI) / 180;
        return {
            ...p,
            color: props.colores[i % props.colores.length],
            dash: `${Math.max(len - 3, 0)} ${c}`,
            offset: -ini,
            tx: 90 + r * Math.cos(rad),
            ty: 94 + r * Math.sin(rad),
            claro: i === 0,
        };
    });
});
</script>

<template>
    <div class="pie-layout">
        <svg viewBox="0 0 180 180" width="165" height="165" role="img">
            <circle v-for="(s, i) in segmentos" :key="i" cx="90" cy="90" :r="r" fill="none" :stroke="s.color"
                    :stroke-width="i === 0 ? 40 : 36" :stroke-dasharray="s.dash" :stroke-dashoffset="s.offset"
                    transform="rotate(-90 90 90)"><title>{{ s.nombre }}: {{ fmtPct(s.pct) }}</title></circle>
            <template v-for="(s, i) in segmentos" :key="'t' + i">
                <text v-if="s.pct >= 7" :x="s.tx" :y="s.ty" text-anchor="middle" font-size="12.5" font-weight="800"
                      :fill="s.claro ? '#ffffff' : '#1c2430'">{{ Math.round(s.pct) }}%</text>
            </template>
        </svg>
        <div class="pie-legend">
            <div v-for="(s, i) in segmentos" :key="'l' + i">
                <span class="pie-sq" :style="{ background: s.color }"></span>
                <span>{{ s.nombre }} ({{ fmtPct(s.pct) }}) · {{ fmtM(s.valor) }}</span>
            </div>
        </div>
    </div>
</template>
