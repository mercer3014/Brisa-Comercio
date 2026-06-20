<script setup>
/** Pastel. Props: series (array de números), categorias (labels), opciones. */
import { computed } from 'vue';
import BaseApexChart from './BaseApexChart.vue';

const props = defineProps({
    series: { type: Array, default: () => [] }, // [n, n, n]
    categorias: { type: Array, default: () => [] }, // labels
    opciones: { type: Object, default: () => ({}) },
    height: { type: [Number, String], default: 320 },
    cargando: { type: Boolean, default: false },
});

const opc = computed(() => ({
    labels: props.categorias,
    legend: { position: 'bottom' },
    stroke: { width: 1, colors: ['#ffffff'] },
    ...props.opciones,
}));
</script>

<template>
    <BaseApexChart type="pie" :series="series" :opciones="opc" :height="height" :cargando="cargando">
        <template #vacio><slot name="vacio">Sin datos para mostrar.</slot></template>
    </BaseApexChart>
</template>
