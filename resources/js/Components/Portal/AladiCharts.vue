<script setup>
/**
 * Gráficos específicos de ALADI (organizacion_id = 2).
 * Evolución anual del bloque (o de un país miembro), comparativa por país
 * y ranking de productos con % acumulado (Pareto) + tabla completa.
 *
 * Los totales anuales se derivan del % acumulado que el top-50 de cada país
 * representa sobre su total (aritmética del propio archivo publicado).
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

const filtros = reactive({ gestion: props.gestionInicial ?? props.gestiones?.[0] ?? null, flujo: '', pais: null });

const paramsRanking = () => ({
    gestion: filtros.gestion,
    limit: 20,
    ...(filtros.flujo ? { flujo: filtros.flujo } : {}),
    ...(filtros.pais ? { pais_id: filtros.pais } : {}),
});
const paramsEvolucion = () => ({ ...(filtros.pais ? { pais_id: filtros.pais } : {}) });
const paramsPaises = () => ({ gestion: filtros.gestion, ...(filtros.flujo ? { flujo: filtros.flujo } : {}) });

const { data: ranking, cargando } = useChartData('/api/v1/charts/aladi/ranking', paramsRanking);
const { data: evolucion, cargando: cEvo } = useChartData('/api/v1/charts/aladi/evolucion', paramsEvolucion);
const { data: paises, cargando: cPais } = useChartData('/api/v1/charts/aladi/paises', paramsPaises);

const items = computed(() => ranking.value?.items ?? []);
const listaPaises = computed(() => paises.value?.meta?.paises ?? []);
const nombrePais = computed(() => listaPaises.value.find((p) => p.id === filtros.pais)?.nombre ?? 'Todos los miembros');

// Evolución: líneas exp/imp + barras de balanza en el mismo eje.
const opcEvolucion = computed(() => ({
    chart: { stacked: false },
    stroke: { curve: 'straight', width: [3, 3, 0] },
    markers: { size: [3.5, 3.5, 0], strokeWidth: 0 },
    plotOptions: { bar: { columnWidth: '60%', borderRadius: 2 } },
    colors: ['#2E7D32', '#C62828', '#A8D0E6'],
    fill: { opacity: [1, 1, 0.9] },
    xaxis: { categories: evolucion.value?.categorias ?? [], labels: { rotate: -45, style: { fontSize: '10px' } } },
    yaxis: { labels: { formatter: ejeCompacto } },
    tooltip: { shared: true, intersect: false, y: { formatter: (v) => fmtUsd(v) } },
    legend: { position: 'top' },
}));

// Países miembros: barras agrupadas exp/imp.
const opcPaises = computed(() => ({
    plotOptions: { bar: { horizontal: false, columnWidth: '60%', borderRadius: 4 } },
    colors: ['#38A169', '#C53030'],
    xaxis: { categories: paises.value?.categorias ?? [], labels: { rotate: -45, style: { fontSize: '11px' } } },
    yaxis: { labels: { formatter: ejeCompacto } },
    tooltip: { y: { formatter: (v) => fmtUsd(v) } },
    legend: { position: 'top' },
}));

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
            <label class="text-xs font-medium text-gris-500">País miembro
                <select v-model.number="filtros.pais" class="campo mt-1 py-2 text-sm w-44">
                    <option :value="null">Todos los miembros</option>
                    <option v-for="p in listaPaises" :key="p.id" :value="p.id">{{ p.nombre }}</option>
                </select>
            </label>
            <p class="text-xs text-gris-400 ml-auto">Los códigos con guiones (ej. 87------) son datos confidenciales.</p>
        </div>

        <ChartCard :titulo="`Evolución anual — ${nombrePais}`"
                   subtitulo="Exportaciones, importaciones y balanza (totales derivados del % acumulado del ranking)"
                   fuente="ALADI" :cargando="cEvo">
            <BaseApexChart type="line" :series="evolucion?.series ?? []" :opciones="opcEvolucion" :height="360" :cargando="cEvo" />
        </ChartCard>

        <ChartCard titulo="Comercio por país miembro" :subtitulo="`Totales derivados · ${filtros.gestion ?? ''}`"
                   fuente="ALADI" :cargando="cPais">
            <BaseApexChart type="bar" :series="paises?.series ?? []" :opciones="opcPaises" :height="360" :cargando="cPais" />
        </ChartCard>

        <ChartCard titulo="Ranking de productos (Pareto)" :subtitulo="`Valor y participación acumulada · ${nombrePais}`"
                   fuente="ALADI" :cargando="cargando">
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
