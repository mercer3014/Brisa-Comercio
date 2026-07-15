<script setup>
import { computed } from 'vue';
import { PALETA_GEODATA } from '../../lib/orgColors';

const props = defineProps({
    type: { type: String, required: true },
    series: { type: Array, default: () => [] },
    opciones: { type: Object, default: () => ({}) },
    height: { type: [Number, String], default: 320 },
    cargando: { type: Boolean, default: false },
    paleta: { type: Array, default: () => PALETA_GEODATA },
    chartId: { type: String, default: '' },
});

function mezclar(base, extra) {
    const out = { ...base };
    for (const key of Object.keys(extra || {})) {
        const value = extra[key];
        out[key] = value && typeof value === 'object' && !Array.isArray(value) && base[key] && typeof base[key] === 'object'
            ? mezclar(base[key], value)
            : value;
    }
    return out;
}

const baseOpciones = computed(() => {
    const comunes = {
        chart: {
            type: props.type,
            toolbar: { show: false },
            zoom: { enabled: false },
            animations: { easing: 'easeinout', speed: 450 },
            ...(props.chartId ? { id: props.chartId } : {}),
        },
        colors: props.paleta?.length ? props.paleta : PALETA_GEODATA,
        dataLabels: { enabled: false },
        legend: {
            show: true,
            position: 'top',
            horizontalAlign: 'left',
            fontSize: '12px',
            fontWeight: 600,
        },
        stroke: { width: 2.5, curve: 'smooth' },
        grid: {
            borderColor: '#e2e8f0',
            strokeDashArray: 4,
            padding: { top: 8, left: 8, right: 8, bottom: 0 },
        },
        xaxis: {
            labels: { style: { colors: '#64748b', fontSize: '11px' } },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: {
            labels: { style: { colors: '#64748b', fontSize: '11px' } },
        },
        tooltip: {
            theme: 'light',
        },
    };

    return comunes;
});

const opcionesFinales = computed(() => {
    return mezclar(baseOpciones.value, props.opciones);
});

const hayDatos = computed(() => {
    if (!Array.isArray(props.series) || props.series.length === 0) {
        return false;
    }

    if (['donut', 'pie', 'radialBar'].includes(props.type)) {
        return props.series.some((valor) => Number(valor) !== 0);
    }

    return props.series.some((serie) => (Array.isArray(serie) ? serie.length : (serie?.data?.length ?? 0)) > 0);
});

</script>

<template>
    <div class="relative w-full" :style="{ minHeight: typeof height === 'number' ? `${height}px` : height }">
        <div v-if="cargando" class="absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-white/70 backdrop-blur-sm">
            <div class="flex flex-col items-center gap-2 text-gris-500">
                <svg class="h-6 w-6 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2.6" />
                    <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                <span class="text-xs font-semibold uppercase tracking-[0.16em]">Cargando</span>
            </div>
        </div>

        <div v-if="!hayDatos && !cargando" class="absolute inset-0 flex items-center justify-center text-sm text-gris-500">
            <slot name="vacio">Sin datos para mostrar.</slot>
        </div>

        <apexchart
            v-show="hayDatos"
            :type="type"
            :height="height"
            :options="opcionesFinales"
            :series="series"
        />
    </div>
</template>
