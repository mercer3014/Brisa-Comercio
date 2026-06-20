<script setup>
/**
 * Gráficos específicos del INE (organizacion_id = 1).
 * Consume /api/v1/charts/* con filtros reactivos de gestión y flujo.
 */
import { reactive, computed } from 'vue';
import ChartCard from '../UI/ChartCard.vue';
import EstadoVacio from '../UI/EstadoVacio.vue';
import BarChart from '../Charts/BarChart.vue';
import TreemapChart from '../Charts/TreemapChart.vue';
import DonutChart from '../Charts/DonutChart.vue';
import AreaChart from '../Charts/AreaChart.vue';
import BaseApexChart from '../Charts/BaseApexChart.vue';
import { useChartData } from '../Composables/useChartData.js';
import { fmtUsd, fmtCompacto, ejeCompacto } from '../../lib/format';

const props = defineProps({
    gestiones: { type: Array, default: () => [] },
    gestionInicial: { type: Number, default: null },
});

const filtros = reactive({
    gestion: props.gestionInicial ?? props.gestiones?.[0] ?? null,
    flujo: 'exp',
});

const paramsFlujo = () => ({ gestion: filtros.gestion, flujo: filtros.flujo, limit: 10 });
const paramsGestion = () => ({ gestion: filtros.gestion });

const { data: mensual, cargando: cMensual } = useChartData('/api/v1/charts/comercio-mensual', paramsGestion);
const { data: seccion, cargando: cSeccion } = useChartData('/api/v1/charts/seccion-arancelaria', paramsFlujo);
const { data: productos, cargando: cProd } = useChartData('/api/v1/charts/top-productos', paramsFlujo);
const { data: paises, cargando: cPais } = useChartData('/api/v1/charts/top-paises', paramsFlujo);
const { data: deptos, cargando: cDepto } = useChartData('/api/v1/charts/top-departamentos', paramsGestion);
const { data: transporte, cargando: cTrans } = useChartData('/api/v1/charts/transporte', paramsGestion);
const { data: tnt, cargando: cTnt } = useChartData('/api/v1/charts/tnt-evolucion');
const { data: evolucion, cargando: cEvol } = useChartData('/api/v1/charts/evolucion-anual');

const flujoLabel = computed(() => (filtros.flujo === 'imp' ? 'Importación' : 'Exportación'));

const opcMensual = computed(() => ({
    stroke: { width: [3, 3, 0], curve: 'smooth' },
    xaxis: { categories: mensual.value?.categorias ?? [] },
    yaxis: { labels: { formatter: ejeCompacto } },
    colors: ['#3182CE', '#C53030', '#38A169'],
    tooltip: { y: { formatter: (v) => fmtUsd(v) } },
    plotOptions: { bar: { columnWidth: '40%', borderRadius: 4 } },
    legend: { position: 'top' },
}));

const opcBarras = (cats) => ({
    plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: '55%', distributed: true } },
    colors: ['#1A4B8C', '#3182CE', '#38A169', '#D69E2E', '#DD6B20', '#805AD5', '#319795', '#C53030'],
    legend: { show: false },
    xaxis: { categories: cats, labels: { formatter: ejeCompacto } },
    yaxis: { labels: { style: { fontSize: '11px' }, maxWidth: 220 } },
    tooltip: { y: { formatter: (v) => fmtUsd(v) } },
});

const opcDonut = { legend: { position: 'bottom' }, tooltip: { y: { formatter: (v) => fmtUsd(v) } } };

const opcTnt = computed(() => ({
    chart: { stacked: true },
    plotOptions: { bar: { columnWidth: '45%', borderRadius: 3 } },
    xaxis: { categories: tnt.value?.categorias ?? [] },
    yaxis: { labels: { formatter: ejeCompacto } },
    tooltip: { y: { formatter: (v) => fmtUsd(v) } },
}));

const opcEvol = computed(() => ({
    xaxis: { categories: evolucion.value?.categorias ?? [] },
    yaxis: { labels: { formatter: ejeCompacto } },
    colors: ['#3182CE', '#C53030'],
    tooltip: { y: { formatter: (v) => fmtUsd(v) } },
}));

// Tabla filtrable de productos (búsqueda local).
import { ref } from 'vue';
const busqueda = ref('');
const filasMensual = computed(() => (mensual.value?.categorias ?? []).map((c, i) => ({
    p: c,
    e: mensual.value?.series?.[0]?.data?.[i] ?? 0,
    i: mensual.value?.series?.[1]?.data?.[i] ?? 0,
    s: mensual.value?.series?.[2]?.data?.[i] ?? 0,
})));
const filasSeccionProductos = computed(() => (productos.value?.categorias ?? []).map((c, i) => ({ producto: c, valor: productos.value?.series?.[0]?.data?.[i] ?? 0 })));
const filasPaises = computed(() => (paises.value?.categorias ?? []).map((c, i) => ({ pais: c, valor: paises.value?.series?.[0]?.data?.[i] ?? 0 })));
const filasDeptos = computed(() => (deptos.value?.categorias ?? []).map((c, i) => ({ d: c, valor: deptos.value?.series?.[0]?.data?.[i] ?? 0 })));
const filasEvolucion = computed(() => (evolucion.value?.categorias ?? []).map((c, i) => ({
    a: c,
    e: evolucion.value?.series?.[0]?.data?.[i] ?? 0,
    i: evolucion.value?.series?.[1]?.data?.[i] ?? 0,
})));
const filasProductos = computed(() => {
    const q = busqueda.value.trim().toLowerCase();
    return filasSeccionProductos.value.filter((f) => !q || f.producto.toLowerCase().includes(q));
});
</script>

<template>
    <div class="space-y-6">
        <!-- Filtros -->
        <div class="tarjeta p-4 flex flex-wrap items-end gap-3">
            <label class="text-xs font-medium text-gris-500">Año
                <select v-model.number="filtros.gestion" class="campo mt-1 py-2 text-sm w-32">
                    <option v-for="g in gestiones" :key="g" :value="g">{{ g }}</option>
                </select>
            </label>
            <label class="text-xs font-medium text-gris-500">Flujo
                <select v-model="filtros.flujo" class="campo mt-1 py-2 text-sm w-40">
                    <option value="exp">Exportación</option>
                    <option value="imp">Importación</option>
                </select>
            </label>
            <p class="text-xs text-gris-400 ml-auto">Los filtros afectan productos, países, sección y transporte.</p>
        </div>

        <!-- Comercio mensual -->
        <ChartCard titulo="Comercio exterior mensual" subtitulo="Exportaciones, importaciones y saldo" fuente="INE — Bolivia" :cargando="cMensual"
            :columnas="[{key:'p',label:'Periodo'},{key:'e',label:'Exp',alinear:'right'},{key:'i',label:'Imp',alinear:'right'},{key:'s',label:'Saldo',alinear:'right'}]"
            :filas="filasMensual">
            <BaseApexChart type="line" :series="mensual?.series ?? []" :opciones="opcMensual" :height="350" :cargando="cMensual" />
        </ChartCard>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Treemap sección arancelaria -->
            <ChartCard :titulo="`Sección arancelaria — ${flujoLabel}`" fuente="INE — Bolivia" :cargando="cSeccion">
                <TreemapChart :series="seccion?.series ?? []" :height="320" :cargando="cSeccion" />
            </ChartCard>

            <!-- Transporte: medio (donut) -->
            <ChartCard titulo="Distribución por medio de transporte" fuente="INE — Bolivia" :cargando="cTrans">
                <DonutChart v-if="(transporte?.medio?.categorias?.length)" :series="transporte?.medio?.series?.[0]?.data ?? []" :categorias="transporte.medio.categorias" :opciones="opcDonut" :height="320" :cargando="cTrans" />
                <EstadoVacio v-else titulo="Sin datos de transporte" mensaje="No hay registros de medio de transporte para este periodo." />
            </ChartCard>

            <!-- Top productos -->
            <ChartCard :titulo="`Top productos — ${flujoLabel}`" fuente="INE — Bolivia" :cargando="cProd"
                :columnas="[{key:'producto',label:'Producto'},{key:'valor',label:'Valor',alinear:'right'}]"
                :filas="filasSeccionProductos">
                <BarChart :series="[{name:'Valor',data:productos?.series?.[0]?.data ?? []}]" :categorias="productos?.categorias ?? []" :opciones="opcBarras(productos?.categorias ?? [])" :height="340" :cargando="cProd" />
            </ChartCard>

            <!-- Top países -->
            <ChartCard :titulo="`Top países — ${flujoLabel}`" fuente="INE — Bolivia" :cargando="cPais"
                :columnas="[{key:'pais',label:'País'},{key:'valor',label:'Valor',alinear:'right'}]"
                :filas="filasPaises">
                <BarChart :series="[{name:'Valor',data:paises?.series?.[0]?.data ?? []}]" :categorias="paises?.categorias ?? []" :opciones="opcBarras(paises?.categorias ?? [])" :height="340" :cargando="cPais" />
            </ChartCard>

            <!-- Top departamentos -->
            <ChartCard titulo="Exportaciones por departamento" fuente="INE — Bolivia" :cargando="cDepto"
                :columnas="[{key:'d',label:'Departamento'},{key:'valor',label:'Valor',alinear:'right'}]"
                :filas="filasDeptos">
                <BarChart :series="[{name:'Exportaciones',data:deptos?.series?.[0]?.data ?? []}]" :categorias="deptos?.categorias ?? []" :opciones="opcBarras(deptos?.categorias ?? [])" :height="320" :cargando="cDepto" />
            </ChartCard>

            <!-- TNT -->
            <ChartCard titulo="Tradicional vs No Tradicional" subtitulo="Exportaciones por clasificación TNT y año" fuente="INE — Bolivia" :cargando="cTnt">
                <BaseApexChart v-if="(tnt?.categorias?.length)" type="bar" :series="tnt?.series ?? []" :opciones="opcTnt" :height="320" :cargando="cTnt" />
                <EstadoVacio v-else titulo="Sin clasificación TNT" mensaje="Los datos cargados aún no incluyen la columna de clasificación Tradicional/No Tradicional." />
            </ChartCard>
        </div>

        <!-- Evolución anual -->
        <ChartCard titulo="Evolución anual" subtitulo="Exportaciones vs importaciones por gestión" fuente="INE — Bolivia" :cargando="cEvol"
            :columnas="[{key:'a',label:'Año'},{key:'e',label:'Exp',alinear:'right'},{key:'i',label:'Imp',alinear:'right'}]"
            :filas="filasEvolucion">
            <AreaChart :series="(evolucion?.series ?? []).slice(0,2)" :categorias="evolucion?.categorias ?? []" :opciones="opcEvol" :apilada="true" :height="320" :cargando="cEvol" />
        </ChartCard>

        <!-- Tabla filtrable -->
        <div class="tarjeta p-5">
            <div class="flex items-center justify-between mb-4 gap-3">
                <h3 class="font-bold text-institucional-900">Detalle de productos — {{ flujoLabel }}</h3>
                <input v-model="busqueda" type="search" placeholder="Buscar producto…" class="campo py-2 text-sm max-w-xs" />
            </div>
            <div class="overflow-x-auto max-h-96 overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs font-semibold text-institucional-500 uppercase tracking-wider border-b border-gris-200 sticky top-0 bg-white">
                        <tr><th class="text-left py-2 px-2">Producto</th><th class="text-right py-2 px-2">Valor (USD)</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gris-100">
                        <tr v-for="(f,i) in filasProductos" :key="i" class="hover:bg-gris-50">
                            <td class="py-2 px-2 text-gris-700">{{ f.producto }}</td>
                            <td class="py-2 px-2 text-right font-medium text-institucional-900">{{ fmtCompacto(f.valor) }}</td>
                        </tr>
                        <tr v-if="!filasProductos.length"><td colspan="2" class="py-8 text-center text-gris-400">Sin resultados.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
