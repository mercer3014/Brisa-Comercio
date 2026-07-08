<script setup>
/**
 * Gráficos específicos de FAOSTAT (organizacion_id = 4).
 * Dominio "Índices comerciales": índices de valor y volumen de exportación e
 * importación por país y producto CPC (base 2014-2016 = 100). No son valores
 * en USD: un índice de 200 significa el doble que el promedio 2014-2016.
 */
import { reactive, computed, watch } from 'vue';
import ChartCard from '../UI/ChartCard.vue';
import EstadoVacio from '../UI/EstadoVacio.vue';
import BaseApexChart from '../Charts/BaseApexChart.vue';
import { useChartData } from '../Composables/useChartData.js';

const filtros = reactive({ pais: null, producto: null, flujo: 'exp' });

const { data: filtrosResp } = useChartData('/api/v1/charts/faostat/filtros', () => ({
    ...(filtros.pais ? { pais_id: filtros.pais } : {}),
}));

const paises = computed(() => filtrosResp.value?.paises ?? []);
const productos = computed(() => filtrosResp.value?.productos ?? []);

// Al llegar los filtros por primera vez, fijar el país por defecto (Bolivia).
watch(filtrosResp, (v) => {
    if (v && filtros.pais === null && v.pais_id) {
        filtros.pais = v.pais_id;
    }
});
// Al cambiar de país, el producto seleccionado puede no existir en el nuevo país.
watch(() => filtros.pais, () => { filtros.producto = null; });

const paramsEvolucion = () => ({
    ...(filtros.pais ? { pais_id: filtros.pais } : {}),
    ...(filtros.producto ? { producto_id: filtros.producto } : {}),
});
const paramsProductos = () => ({
    ...(filtros.pais ? { pais_id: filtros.pais } : {}),
    flujo: filtros.flujo,
    limit: 10,
});

const { data: evolucion, cargando: cEvo } = useChartData('/api/v1/charts/faostat/evolucion', paramsEvolucion);
const { data: topProductos, cargando: cProd } = useChartData('/api/v1/charts/faostat/productos', paramsProductos);

const hay = (d) => d?.meta?.hay_datos === true && (d?.categorias?.length ?? 0) > 0;

const fmtIdx = (v) => (v == null ? '—' : Number(v).toLocaleString('es-BO', { maximumFractionDigits: 1 }));

const opcEvolucion = computed(() => ({
    stroke: { curve: 'straight', width: 2.5 },
    markers: { size: 0, hover: { size: 4 } },
    colors: ['#2E7D32', '#66BB6A', '#A5D6A7', '#C62828', '#EF5350', '#EF9A9A'],
    xaxis: { categories: evolucion.value?.categorias ?? [], labels: { rotate: -45, style: { fontSize: '10px' } } },
    yaxis: { title: { text: 'Índice (2014-2016 = 100)' }, labels: { formatter: fmtIdx } },
    annotations: { yaxis: [{ y: 100, borderColor: '#94a3b8', strokeDashArray: 5, label: { text: 'Base 2014-2016 = 100', style: { color: '#64748b', background: '#f8fafc' } } }] },
    tooltip: { shared: true, intersect: false, y: { formatter: fmtIdx } },
    legend: { position: 'bottom', fontSize: '11px' },
}));

const opcProductos = computed(() => ({
    plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: '55%', distributed: true } },
    colors: ['#1A4B8C', '#3182CE', '#38A169', '#D69E2E', '#DD6B20', '#805AD5', '#319795', '#C53030', '#1A2332', '#E53E3E'],
    legend: { show: false },
    xaxis: { categories: topProductos.value?.categorias ?? [], labels: { formatter: fmtIdx } },
    yaxis: { labels: { style: { fontSize: '10px' }, maxWidth: 240 } },
    tooltip: { y: { formatter: (v) => `${fmtIdx(v)} (índice)` } },
}));
</script>

<template>
    <div class="space-y-6">
        <div class="tarjeta p-4 flex flex-wrap items-end gap-3">
            <label class="text-xs font-medium text-gris-500">País
                <select v-model.number="filtros.pais" class="campo mt-1 py-2 text-sm w-56">
                    <option v-for="p in paises" :key="p.id" :value="p.id">{{ p.nombre }}</option>
                </select>
            </label>
            <label class="text-xs font-medium text-gris-500">Producto (CPC)
                <select v-model.number="filtros.producto" class="campo mt-1 py-2 text-sm w-64">
                    <option :value="null">El de mayor serie histórica</option>
                    <option v-for="p in productos" :key="p.id" :value="p.id">{{ p.nombre }}</option>
                </select>
            </label>
            <label class="text-xs font-medium text-gris-500">Flujo (top productos)
                <select v-model="filtros.flujo" class="campo mt-1 py-2 text-sm w-40">
                    <option value="exp">Exportación</option>
                    <option value="imp">Importación</option>
                </select>
            </label>
            <p class="text-xs text-gris-400 ml-auto max-w-xs">
                FAOSTAT publica índices (base 2014-2016 = 100), no valores en USD.
            </p>
        </div>

        <ChartCard :titulo="`Índices comerciales — ${evolucion?.meta?.producto ?? 'producto'}`"
                   :subtitulo="`${evolucion?.meta?.pais ?? ''} · valor y volumen de exportación e importación`"
                   fuente="FAOSTAT" :cargando="cEvo">
            <BaseApexChart v-if="hay(evolucion)" type="line" :series="evolucion.series" :opciones="opcEvolucion" :height="380" :cargando="cEvo" />
            <EstadoVacio v-else mensaje="Sin series para este país y producto." />
        </ChartCard>

        <ChartCard :titulo="`Top 10 productos por índice de ${filtros.flujo === 'imp' ? 'importación' : 'exportación'}`"
                   :subtitulo="`${topProductos?.meta?.pais ?? ''} · ${topProductos?.meta?.gestion ?? ''} · los que más crecieron frente a la base 2014-2016`"
                   fuente="FAOSTAT" :cargando="cProd">
            <BaseApexChart v-if="hay(topProductos)" type="bar" :series="topProductos.series" :opciones="opcProductos" :height="380" :cargando="cProd" />
            <EstadoVacio v-else mensaje="Sin datos para estos filtros." />
        </ChartCard>
    </div>
</template>
