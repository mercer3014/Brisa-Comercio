<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    organizacionDefecto: Number,
    organizaciones: Array,
    gestiones: Array,
});

const orgId = ref(props.organizacionDefecto);
const gestion = ref(props.gestiones?.[0] ?? null);
const tab = ref('general');
const cargando = ref(false);
const d = ref(null); // datos

// Organizaciones cuya arquitectura ya sabe leer este dashboard (cada una con
// su propia fuente: INE = microdato, ALADI = rankings top-50 por pais,
// MERCOSUR = series por zona/producto). El resto (FAOSTAT) todavia usa su
// panel dedicado en /organizaciones/{id}.
const ORGS_SOPORTADAS = [1, 2, 3];
// MERCOSUR y ALADI no tienen desagregacion por departamento ni por medio de
// transporte (eso es propio del microdato del INE), asi que esa pestania no aplica.
const tabsBase = [
    { key: 'general', label: 'General' },
    { key: 'exportaciones', label: 'Exportaciones' },
    { key: 'importaciones', label: 'Importaciones' },
    { key: 'pais', label: 'Por pais' },
    { key: 'producto', label: 'Por producto' },
    { key: 'balanza', label: 'Balanza comercial' },
    { key: 'logistico', label: 'Logistico' },
];
const tabs = computed(() => [2, 3].includes(orgId.value) ? tabsBase.filter((t) => t.key !== 'logistico') : tabsBase);

async function cargar() {
    cargando.value = true;
    try {
        if (!ORGS_SOPORTADAS.includes(orgId.value)) {
            d.value = { kpis: {} };
            return;
        }
        const { data } = await axios.post('/admin/dashboards/datos', {
            organizacion_id: orgId.value,
            gestion: gestion.value,
        });
        d.value = data;
    } finally {
        cargando.value = false;
    }
}

watch(orgId, () => {
    if (!tabs.value.some((t) => t.key === tab.value)) {
        tab.value = 'general';
    }
});
watch([orgId, gestion], cargar);
onMounted(cargar);

const fmt = (n) => new Intl.NumberFormat('es-BO', { maximumFractionDigits: 0 }).format(n || 0);
const fmtUsd = (n) => '$ ' + fmt(n);
const fmtM = (n) => '$ ' + new Intl.NumberFormat('es-BO', { maximumFractionDigits: 1 }).format((n || 0) / 1e6) + ' M';

// ---- Configuracion de graficos ----
const colorMarca = '#2563eb';
const colorVerde = '#059669';
const colorAmbar = '#d97706';

const optsBarras = (categorias, horizontal = false) => ({
    chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit' },
    plotOptions: { bar: { horizontal, borderRadius: 4, columnWidth: '60%' } },
    dataLabels: { enabled: false },
    xaxis: { categories: categorias, labels: { style: { fontSize: '11px' } } },
    colors: [colorMarca],
    grid: { borderColor: '#f1f5f9' },
});

// Evolucion mensual
const evoMensual = computed(() => {
    const e = d.value?.evolucion_mensual ?? [];
    return {
        series: [
            { name: 'Valor (USD)', type: 'column', data: e.map((x) => Math.round(x.valor)) },
            { name: 'Peso (kg)', type: 'line', data: e.map((x) => Math.round(x.peso)) },
        ],
        options: {
            chart: { toolbar: { show: false }, fontFamily: 'inherit' },
            stroke: { width: [0, 3] },
            colors: [colorMarca, colorAmbar],
            xaxis: { categories: e.map((x) => x.periodo), labels: { rotate: -45, style: { fontSize: '10px' } } },
            yaxis: [{ title: { text: 'Valor' }, labels: { formatter: (v) => fmt(v) } }, { opposite: true, title: { text: 'Peso' }, labels: { formatter: (v) => fmt(v) } }],
            dataLabels: { enabled: false },
            grid: { borderColor: '#f1f5f9' },
        },
    };
});

// Balanza anual
const balanzaAnual = computed(() => {
    const e = d.value?.evolucion_anual ?? [];
    return {
        series: [
            { name: 'Exportaciones', data: e.map((x) => Math.round(x.expo)) },
            { name: 'Importaciones', data: e.map((x) => Math.round(x.impo)) },
            { name: 'Balanza', data: e.map((x) => Math.round(x.balanza)) },
        ],
        options: {
            chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit' },
            plotOptions: { bar: { borderRadius: 4, columnWidth: '60%' } },
            colors: [colorVerde, colorAmbar, colorMarca],
            xaxis: { categories: e.map((x) => x.gestion) },
            dataLabels: { enabled: false },
            grid: { borderColor: '#f1f5f9' },
            legend: { position: 'top' },
        },
    };
});

const topPaises = computed(() => {
    const e = d.value?.top_paises ?? [];
    return { series: [{ name: 'Valor', data: e.map((x) => Math.round(x.valor)) }], options: optsBarras(e.map((x) => x.label), true) };
});
const topProductos = computed(() => {
    const e = d.value?.top_productos ?? [];
    return { series: [{ name: 'Valor', data: e.map((x) => Math.round(x.valor)) }], options: { ...optsBarras(e.map((x) => x.label), true), colors: [colorVerde] } };
});
const distZona = computed(() => {
    const e = d.value?.distribucion_zona ?? [];
    return { series: e.map((x) => Math.round(x.valor)), options: { chart: { type: 'donut', fontFamily: 'inherit' }, labels: e.map((x) => x.label), legend: { position: 'bottom' }, colors: ['#2563eb', '#059669', '#d97706', '#7c3aed', '#dc2626', '#0891b2'] } };
});
const distDepto = computed(() => {
    const e = d.value?.distribucion_departamento ?? [];
    return { series: [{ name: 'Valor', data: e.map((x) => Math.round(x.valor)) }], options: { ...optsBarras(e.map((x) => x.label)), colors: [colorAmbar] } };
});
const distMedio = computed(() => {
    const e = d.value?.distribucion_medio ?? [];
    return { series: e.map((x) => Math.round(x.valor)), options: { chart: { type: 'pie', fontFamily: 'inherit' }, labels: e.map((x) => x.label), legend: { position: 'bottom' } } };
});

const k = computed(() => d.value?.kpis ?? {});

// INE, ALADI y MERCOSUR ya se leen aqui mismo, cada uno con su arquitectura.
// FAOSTAT todavia no tiene un agregador equivalente: manda a su propio panel
// en el portal publico en vez de mostrar una pantalla vacia.
const orgActual = computed(() => props.organizaciones.find((o) => o.organizacion_id === orgId.value));
const sinMicrodato = computed(() => !ORGS_SOPORTADAS.includes(orgId.value));
</script>

<template>
    <Head title="Dashboards" />

    <div class="max-w-7xl mx-auto">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Dashboards</h1>
                <p class="text-slate-500 text-sm">Indicadores y visualizaciones del comercio exterior.</p>
            </div>
            <div class="flex gap-2">
                <select v-model="orgId" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option v-for="o in organizaciones" :key="o.organizacion_id" :value="o.organizacion_id">{{ o.nombre }}</option>
                </select>
                <select v-model="gestion" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option :value="null">Todas las gestiones</option>
                    <option v-for="g in gestiones" :key="g" :value="g">{{ g }}</option>
                </select>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-1 mb-5 border-b border-slate-200 overflow-x-auto">
            <button v-for="t in tabs" :key="t.key" @click="tab = t.key"
                    class="px-4 py-2 text-sm font-medium border-b-2 whitespace-nowrap transition"
                    :class="tab === t.key ? 'border-marca-600 text-marca-700' : 'border-transparent text-slate-500 hover:text-marca-600'">
                {{ t.label }}
            </button>
        </div>

        <div v-if="!d" class="text-center py-20 text-slate-400">Cargando indicadores...</div>

        <div v-else-if="sinMicrodato" class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-center">
            <p class="text-amber-800 font-medium">
                Este dashboard solo lee el microdato del INE. {{ orgActual?.nombre ?? 'Esta organizacion' }} guarda sus
                datos en un panel propio, con sus graficos y KPIs.
            </p>
            <a :href="`/organizaciones/${orgId}`" target="_blank"
               class="inline-block mt-3 rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">
                Ver panel de {{ orgActual?.sigla ?? orgActual?.nombre }}
            </a>
        </div>

        <div v-else class="space-y-5">
            <!-- KPIs (siempre visibles) -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <div class="text-xs text-slate-500">Valor total</div>
                    <div class="text-xl font-bold text-marca-700">{{ fmtM(k.valor_total) }}</div>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <div class="text-xs text-slate-500">Exportaciones</div>
                    <div class="text-xl font-bold text-emerald-600">{{ fmtM(k.valor_exportacion) }}</div>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <div class="text-xs text-slate-500">Importaciones</div>
                    <div class="text-xl font-bold text-amber-600">{{ fmtM(k.valor_importacion) }}</div>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <div class="text-xs text-slate-500">Balanza comercial</div>
                    <div class="text-xl font-bold" :class="k.balanza_comercial >= 0 ? 'text-emerald-600' : 'text-red-600'">{{ fmtM(k.balanza_comercial) }}</div>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <div class="text-xs text-slate-500">Precio implicito $/kg</div>
                    <div class="text-xl font-bold text-slate-800">{{ k.precio_implicito ?? '—' }}</div>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <div class="text-xs text-slate-500">Var. interanual</div>
                    <div class="text-xl font-bold" :class="(k.variacion_interanual ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-600'">
                        <span v-if="k.variacion_interanual !== null">{{ k.variacion_interanual > 0 ? '+' : '' }}{{ k.variacion_interanual }}%</span>
                        <span v-else>—</span>
                    </div>
                </div>
            </div>

            <!-- GENERAL -->
            <div v-show="tab === 'general'" class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <div class="bg-white rounded-xl border border-slate-200 p-4 lg:col-span-2">
                    <h3 class="font-semibold text-slate-700 mb-3">Evolucion mensual (valor y peso)</h3>
                    <apexchart height="300" :options="evoMensual.options" :series="evoMensual.series" />
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <h3 class="font-semibold text-slate-700 mb-3">Distribucion por zona</h3>
                    <apexchart height="300" type="donut" :options="distZona.options" :series="distZona.series" />
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <h3 class="font-semibold text-slate-700 mb-3">Top 10 paises</h3>
                    <apexchart height="300" type="bar" :options="topPaises.options" :series="topPaises.series" />
                </div>
            </div>

            <!-- EXPORTACIONES / IMPORTACIONES -->
            <div v-show="tab === 'exportaciones' || tab === 'importaciones'" class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <div class="bg-white rounded-xl border border-slate-200 p-4 lg:col-span-2">
                    <h3 class="font-semibold text-slate-700 mb-3">Balanza anual (exportaciones vs importaciones)</h3>
                    <apexchart height="300" type="bar" :options="balanzaAnual.options" :series="balanzaAnual.series" />
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <h3 class="font-semibold text-slate-700 mb-3">Top 10 productos</h3>
                    <apexchart height="320" type="bar" :options="topProductos.options" :series="topProductos.series" />
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <h3 class="font-semibold text-slate-700 mb-3">Top 10 paises</h3>
                    <apexchart height="320" type="bar" :options="topPaises.options" :series="topPaises.series" />
                </div>
            </div>

            <!-- POR PAIS -->
            <div v-show="tab === 'pais'" class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <h3 class="font-semibold text-slate-700 mb-3">Top 10 paises por valor</h3>
                    <apexchart height="340" type="bar" :options="topPaises.options" :series="topPaises.series" />
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <h3 class="font-semibold text-slate-700 mb-3">Participacion por pais</h3>
                    <table class="w-full text-sm">
                        <thead class="text-slate-500"><tr><th class="text-left py-1">Pais</th><th class="text-right">Valor</th><th class="text-right">%</th></tr></thead>
                        <tbody>
                            <tr v-for="p in d.participacion_pais" :key="p.label" class="border-t border-slate-100">
                                <td class="py-1.5">{{ p.label }}</td>
                                <td class="text-right">{{ fmtUsd(p.valor) }}</td>
                                <td class="text-right font-medium text-marca-700">{{ p.porcentaje }}%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- POR PRODUCTO -->
            <div v-show="tab === 'producto'" class="bg-white rounded-xl border border-slate-200 p-4">
                <h3 class="font-semibold text-slate-700 mb-3">Top 10 productos por valor</h3>
                <apexchart height="380" type="bar" :options="topProductos.options" :series="topProductos.series" />
            </div>

            <!-- BALANZA -->
            <div v-show="tab === 'balanza'" class="bg-white rounded-xl border border-slate-200 p-4">
                <h3 class="font-semibold text-slate-700 mb-3">Balanza comercial por gestion</h3>
                <apexchart height="360" type="bar" :options="balanzaAnual.options" :series="balanzaAnual.series" />
            </div>

            <!-- LOGISTICO -->
            <div v-show="tab === 'logistico'" class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <h3 class="font-semibold text-slate-700 mb-3">Distribucion por medio de transporte</h3>
                    <apexchart height="320" type="pie" :options="distMedio.options" :series="distMedio.series" />
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <h3 class="font-semibold text-slate-700 mb-3">Distribucion por departamento</h3>
                    <apexchart height="320" type="bar" :options="distDepto.options" :series="distDepto.series" />
                </div>
            </div>
        </div>
    </div>
</template>
