<script setup>
/** Área (apilable). Props: series, categorías, opciones, apilada. */
import { computed } from 'vue';
import BaseApexChart from './BaseApexChart.vue';

const props = defineProps({
    series: { type: Array, default: () => [] },
    categorias: { type: Array, default: () => [] },
    opciones: { type: Object, default: () => ({}) },
    apilada: { type: Boolean, default: false },
    height: { type: [Number, String], default: 320 },
    cargando: { type: Boolean, default: false },
});

const opc = computed(() => ({
    chart: { stacked: props.apilada },
    stroke: { curve: 'smooth', width: 2.2 },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.22, opacityTo: 0.04, stops: [0, 100] } },
    xaxis: { categories: props.categorias },
    yaxis: {},
    ...props.opciones,
}));
</script>

<template>
    <BaseApexChart type="area" :series="series" :opciones="opc" :height="height" :cargando="cargando">
        <template #vacio><slot name="vacio">Sin datos para mostrar.</slot></template>
    </BaseApexChart>
</template>
