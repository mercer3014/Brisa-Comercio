<script setup>
/** Barras (vertical u horizontal). Props: series, categorías, opciones, horizontal. */
import { computed } from 'vue';
import BaseApexChart from './BaseApexChart.vue';

const props = defineProps({
    series: { type: Array, default: () => [] },
    categorias: { type: Array, default: () => [] },
    opciones: { type: Object, default: () => ({}) },
    horizontal: { type: Boolean, default: false },
    height: { type: [Number, String], default: 320 },
    cargando: { type: Boolean, default: false },
});

const opc = computed(() => ({
    plotOptions: { bar: { horizontal: props.horizontal, borderRadius: 0, borderRadiusApplication: 'end', columnWidth: '58%' } },
    xaxis: { categories: props.categorias },
    yaxis: {},
    ...props.opciones,
}));
</script>

<template>
    <BaseApexChart type="bar" :series="series" :opciones="opc" :height="height" :cargando="cargando">
        <template #vacio><slot name="vacio">Sin datos para mostrar.</slot></template>
    </BaseApexChart>
</template>
