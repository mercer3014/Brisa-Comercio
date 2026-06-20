<script setup>
/** Líneas. Props: series, categorias, opciones. */
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
    stroke: { curve: 'smooth', width: 2.4 },
    markers: { size: 0, hover: { size: 4 } },
    xaxis: { categories: props.categorias },
    yaxis: {},
    ...props.opciones,
}));
</script>

<template>
    <BaseApexChart type="line" :series="series" :opciones="opc" :height="height" :cargando="cargando">
        <template #vacio><slot name="vacio">Sin datos para mostrar.</slot></template>
    </BaseApexChart>
</template>
