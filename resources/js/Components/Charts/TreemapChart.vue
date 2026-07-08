<script setup>
/**
 * Treemap. Acepta:
 *  - series ya formateada [{ data: [{x,y}] }], o
 *  - categorías + valores planos que se convierten a [{x,y}].
 */
import { computed } from 'vue';
import BaseApexChart from './BaseApexChart.vue';

const props = defineProps({
    series: { type: Array, default: () => [] },
    categorias: { type: Array, default: () => [] },
    valores: { type: Array, default: () => [] },
    opciones: { type: Object, default: () => ({}) },
    height: { type: [Number, String], default: 340 },
    cargando: { type: Boolean, default: false },
});

const serieFinal = computed(() => {
    if (props.series.length) return props.series;
    if (props.categorias.length) {
        return [{ data: props.categorias.map((x, i) => ({ x, y: props.valores[i] ?? 0 })) }];
    }
    return [];
});

const opc = computed(() => ({
    legend: { show: false },
    plotOptions: { treemap: { distributed: true, enableShades: false, borderRadius: 0 } },
    dataLabels: { enabled: true, style: { fontSize: '11px', fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif' } },
    ...props.opciones,
}));
</script>

<template>
    <BaseApexChart type="treemap" :series="serieFinal" :opciones="opc" :height="height" :cargando="cargando">
        <template #vacio><slot name="vacio">Sin datos para mostrar.</slot></template>
    </BaseApexChart>
</template>
