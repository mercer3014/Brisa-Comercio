<script setup>
/**
 * Gráficos específicos del MERCOSUR (organizacion_id = 3).
 * Consume /api/v1/charts/mercosur/* con filtro de gestión.
 */
import { reactive, computed } from 'vue';
import ChartCard from '../UI/ChartCard.vue';
import BarChart from '../Charts/BarChart.vue';
import BaseApexChart from '../Charts/BaseApexChart.vue';
import { useChartData } from '../Composables/useChartData.js';
import { fmtUsd, fmtCompacto, ejeCompacto } from '../../lib/format';

const props = defineProps({
    gestiones: { type: Array, default: () => [] },
    gestionInicial: { type: Number, default: null },
});

const filtros = reactive({ gestion: props.gestionInicial ?? props.gestiones?.[0] ?? null });
const params = () => ({ gestion: filtros.gestion, limit: 10 });

const { data: zona, cargando: cZona } = useChartData('/api/v1/charts/mercosur/zona', params);
const { data: balanza, cargando: cBal } = useChartData('/api/v1/charts/mercosur/balanza', params);
const { data: productos, cargando: cProd } = useChartData('/api/v1/charts/mercosur/productos', params);
const { data: paises, cargando: cPais } = useChartData('/api/v1/charts/mercosur/paises', params);

const opcGrupo = (cats) => ({
    plotOptions: { bar: { horizontal: false, columnWidth: '55%', borderRadius: 4 } },
    colors: ['#38A169', '#C53030'],
    xaxis: { categories: cats, labels: { style: { fontSize: '11px' } } },
    yaxis: { labels: { formatter: ejeCompacto } },
    tooltip: { y: { formatter: (v) => fmtUsd(v) } },
    legend: { position: 'top' },
});

const opcBalanza = computed(() => {
    const datos = balanza.value?.series?.[0]?.data ?? [];
    return {
        plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '50%', distributed: true, colors: { ranges: [{ from: -1e18, to: 0, color: '#C53030' }, { from: 0, to: 1e18, color: '#38A169' }] } } },
        colors: datos.map((v) => (v >= 0 ? '#38A169' : '#C53030')),
        legend: { show: false },
        xaxis: { categories: balanza.value?.categorias ?? [], labels: { formatter: ejeCompacto } },
        tooltip: { y: { formatter: (v) => fmtUsd(v) } },
    };
});

const opcProd = computed(() => ({
    plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: '55%', distributed: true } },
    colors: ['#1A4B8C', '#3182CE', '#38A169', '#D69E2E', '#DD6B20', '#805AD5', '#319795', '#C53030', '#1A2332', '#E53E3E'],
    legend: { show: false },
    xaxis: { categories: productos.value?.categorias ?? [], labels: { formatter: ejeCompacto } },
    yaxis: { labels: { style: { fontSize: '10px' }, maxWidth: 260 } },
    tooltip: { y: { formatter: (v) => fmtUsd(v) } },
}));

const filasZona = computed(() => (zona.value?.categorias ?? []).map((c, i) => ({
    z: c,
    e: zona.value?.series?.[0]?.data?.[i] ?? 0,
    i: zona.value?.series?.[1]?.data?.[i] ?? 0,
})));
const filasBalanza = computed(() => (balanza.value?.categorias ?? []).map((c, i) => ({
    z: c,
    b: balanza.value?.series?.[0]?.data?.[i] ?? 0,
})));
const filasProductos = computed(() => (productos.value?.categorias ?? []).map((c, i) => ({
    p: c,
    v: productos.value?.series?.[0]?.data?.[i] ?? 0,
})));
const filasPaises = computed(() => (paises.value?.categorias ?? []).map((c, i) => ({
    p: c,
    e: paises.value?.series?.[0]?.data?.[i] ?? 0,
    i: paises.value?.series?.[1]?.data?.[i] ?? 0,
})));
</script>

<template>
    <div class="space-y-6">
        <div class="tarjeta p-4 flex flex-wrap items-end gap-3">
            <label class="text-xs font-medium text-gris-500">Año
                <select v-model.number="filtros.gestion" class="campo mt-1 py-2 text-sm w-32">
                    <option v-for="g in gestiones" :key="g" :value="g">{{ g }}</option>
                </select>
            </label>
            <p class="text-xs text-gris-400 ml-auto">Series anuales por zona geoeconómica y país.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <ChartCard titulo="Comercio por zona geoeconómica" subtitulo="Exportaciones vs importaciones" fuente="MERCOSUR" :cargando="cZona"
                :columnas="[{key:'z',label:'Zona'},{key:'e',label:'Exp',alinear:'right'},{key:'i',label:'Imp',alinear:'right'}]"
                :filas="filasZona">
                <BarChart :series="zona?.series ?? []" :categorias="zona?.categorias ?? []" :opciones="opcGrupo(zona?.categorias ?? [])" :height="340" :cargando="cZona" />
            </ChartCard>

            <ChartCard titulo="Balanza comercial por zona" fuente="MERCOSUR" :cargando="cBal"
                :columnas="[{key:'z',label:'Zona'},{key:'b',label:'Balanza',alinear:'right'}]"
                :filas="filasBalanza">
                <BarChart :series="balanza?.series ?? []" :categorias="balanza?.categorias ?? []" :opciones="opcBalanza" :height="340" :cargando="cBal" />
            </ChartCard>
        </div>

        <ChartCard titulo="Top productos NCM exportados" fuente="MERCOSUR" :cargando="cProd"
            :columnas="[{key:'p',label:'Producto NCM'},{key:'v',label:'Exportaciones',alinear:'right'}]"
            :filas="filasProductos">
            <BarChart :series="productos?.series ?? []" :categorias="productos?.categorias ?? []" :opciones="opcProd" :height="Math.max(320, (productos?.categorias?.length ?? 0) * 38)" :cargando="cProd" />
        </ChartCard>

        <ChartCard titulo="Comercio por país" subtitulo="Exportaciones vs importaciones por socio" fuente="MERCOSUR" :cargando="cPais"
            :columnas="[{key:'p',label:'País'},{key:'e',label:'Exp',alinear:'right'},{key:'i',label:'Imp',alinear:'right'}]"
            :filas="filasPaises">
            <BarChart :series="paises?.series ?? []" :categorias="paises?.categorias ?? []" :opciones="opcGrupo(paises?.categorias ?? [])" :height="340" :cargando="cPais" />
        </ChartCard>
    </div>
</template>
