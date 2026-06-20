<script setup>
/**
 * Gráficos específicos de ALADI (organizacion_id = 2).
 * Ranking de productos con % acumulado (Pareto) + tabla completa.
 */
import { reactive, computed } from 'vue';
import ChartCard from '../UI/ChartCard.vue';
import RankingTable from '../UI/RankingTable.vue';
import BaseApexChart from '../Charts/BaseApexChart.vue';
import { useChartData } from '../Composables/useChartData.js';
import { fmtUsd, ejeCompacto } from '../../lib/format';

const props = defineProps({
    gestiones: { type: Array, default: () => [] },
    gestionInicial: { type: Number, default: null },
});

const filtros = reactive({ gestion: props.gestionInicial ?? props.gestiones?.[0] ?? null, flujo: '' });
const params = () => ({ gestion: filtros.gestion, limit: 20, ...(filtros.flujo ? { flujo: filtros.flujo } : {}) });

const { data: ranking, cargando } = useChartData('/api/v1/charts/aladi/ranking', params);

const items = computed(() => ranking.value?.items ?? []);

// Pareto: barras de valor + línea de % acumulado en eje secundario.
const opcPareto = computed(() => ({
    chart: { stacked: false },
    stroke: { width: [0, 3], curve: 'smooth' },
    plotOptions: { bar: { columnWidth: '55%', borderRadius: 3 } },
    colors: ['#1A4B8C', '#C53030'],
    xaxis: { categories: ranking.value?.categorias ?? [], labels: { style: { fontSize: '10px' }, rotate: -45 } },
    yaxis: [
        { title: { text: 'Valor (USD)' }, labels: { formatter: ejeCompacto } },
        { opposite: true, max: 100, title: { text: '% acumulado' }, labels: { formatter: (v) => `${Math.round(v)}%` } },
    ],
    tooltip: { shared: true, intersect: false, y: { formatter: (v, { seriesIndex }) => (seriesIndex === 1 ? `${v}%` : fmtUsd(v)) } },
    legend: { position: 'top' },
}));
</script>

<template>
    <div class="space-y-6">
        <div class="tarjeta p-4 flex flex-wrap items-end gap-3">
            <label class="text-xs font-medium text-gris-500">Año
                <select v-model.number="filtros.gestion" class="campo mt-1 py-2 text-sm w-32">
                    <option v-for="g in gestiones" :key="g" :value="g">{{ g }}</option>
                </select>
            </label>
            <label class="text-xs font-medium text-gris-500">Flujo
                <select v-model="filtros.flujo" class="campo mt-1 py-2 text-sm w-40">
                    <option value="">Todos</option>
                    <option value="exp">Exportación</option>
                    <option value="imp">Importación</option>
                </select>
            </label>
            <p class="text-xs text-gris-400 ml-auto">Los códigos con guiones (ej. 87------) son datos confidenciales.</p>
        </div>

        <ChartCard titulo="Ranking de productos (Pareto)" subtitulo="Valor y participación acumulada" fuente="ALADI" :cargando="cargando">
            <BaseApexChart type="line" :series="ranking?.series ?? []" :opciones="opcPareto" :height="380" :cargando="cargando" />
        </ChartCard>

        <div class="tarjeta p-5">
            <h3 class="font-bold text-institucional-900 mb-4">Ranking completo</h3>
            <RankingTable :filas="items" unidad="USD" :mostrar-acumulado="true" />
            <div v-if="items.some(i => i.es_confidencial)" class="mt-3 text-xs text-gris-500 flex items-center gap-2">
                <span class="badge badge-neutro">conf.</span> Productos con código parcialmente oculto por confidencialidad estadística.
            </div>
        </div>
    </div>
</template>
