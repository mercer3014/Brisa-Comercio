<script setup>
/** Dona. Props: series (números), categorias (labels), opciones. */
import { computed } from 'vue';
import BaseApexChart from './BaseApexChart.vue';

const props = defineProps({
    series: { type: Array, default: () => [] },
    categorias: { type: Array, default: () => [] },
    opciones: { type: Object, default: () => ({}) },
    height: { type: [Number, String], default: 320 },
    cargando: { type: Boolean, default: false },
});

const opc = computed(() => ({
    labels: props.categorias,
    legend: { position: 'bottom' },
    stroke: { width: 1, colors: ['#ffffff'] },
    plotOptions: {
        pie: {
            donut: {
                size: '68%',
                labels: {
                    show: true,
                    value: { fontFamily: 'IBM Plex Mono, monospace' },
                    total: {
                        show: true,
                        label: 'TOTAL',
                        fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
                        color: '#64748b',
                    },
                },
            },
        },
    },
    ...props.opciones,
}));
</script>

<template>
    <BaseApexChart type="donut" :series="series" :opciones="opc" :height="height" :cargando="cargando">
        <template #vacio><slot name="vacio">Sin datos para mostrar.</slot></template>
    </BaseApexChart>
</template>
