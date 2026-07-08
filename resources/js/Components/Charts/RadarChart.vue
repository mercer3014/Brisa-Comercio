<script setup>
/** Radar. Props: series, categorías, opciones. */
import { computed } from 'vue';
import BaseApexChart from './BaseApexChart.vue';

const props = defineProps({
    series: { type: Array, default: () => [] },
    categorias: { type: Array, default: () => [] },
    opciones: { type: Object, default: () => ({}) },
    height: { type: [Number, String], default: 340 },
    cargando: { type: Boolean, default: false },
});

const opc = computed(() => ({
    xaxis: { categories: props.categorias, labels: { style: { colors: Array(props.categorias.length).fill('#64748b'), fontSize: '11px', fontFamily: 'ui-monospace, SFMono-Regular, monospace' } } },
    stroke: { width: 2 },
    fill: { opacity: 0.12 },
    markers: { size: 3 },
    ...props.opciones,
}));
</script>

<template>
    <BaseApexChart type="radar" :series="series" :opciones="opc" :height="height" :cargando="cargando">
        <template #vacio><slot name="vacio">Sin datos para mostrar.</slot></template>
    </BaseApexChart>
</template>
