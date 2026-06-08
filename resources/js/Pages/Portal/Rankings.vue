<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    organizaciones: { type: Array, default: () => [] },
    gestiones: { type: Array, default: () => [] },
    organizacionDefecto: { type: Number, default: 1 },
    gestionInicial: { type: Number, default: null },
});

const tab = ref('ranking'); // 'ranking' | 'comparador'

// --- Filtros del ranking ---
const f = reactive({
    organizacion_id: props.organizacionDefecto,
    gestion: props.gestionInicial ?? props.gestiones?.[0] ?? null,
    flujo: 1,
    dimension: 'producto',
    metrica: 'valor',
    limite: 10,
});

const ranking = ref(null);
const cargandoR = ref(false);

async function cargarRanking() {
    if (!f.gestion) return;
    cargandoR.value = true;
    try {
        const { data } = await axios.get('/rankings/datos', { params: { ...f } });
        ranking.value = data;
    } finally {
        cargandoR.value = false;
    }
}

function exportar(formato) {
    const q = new URLSearchParams({ ...f, formato }).toString();
    window.location.href = `/rankings/exportar?${q}`;
}

// --- Comparador ---
const c = reactive({
    modo: 'anios', // 'anios' | 'flujos'
    organizacion_id: props.organizacionDefecto,
    dimension: 'producto',
    flujo: 1,
    anio_a: props.gestiones?.[1] ?? props.gestiones?.[0] ?? null,
    anio_b: props.gestiones?.[0] ?? null,
    gestion: props.gestionInicial ?? props.gestiones?.[0] ?? null,
    limite: 10,
});

const comparador = ref(null);
const cargandoC = ref(false);

async function cargarComparador() {
    cargandoC.value = true;
    try {
        const { data } = await axios.get('/rankings/comparar', { params: { ...c } });
        comparador.value = data;
    } finally {
        cargandoC.value = false;
    }
}

onMounted(cargarRanking);
watch([() => f.organizacion_id, () => f.gestion, () => f.flujo, () => f.dimension, () => f.metrica, () => f.limite], cargarRanking);
watch(tab, (t) => { if (t === 'comparador' && !comparador.value) cargarComparador(); });

// --- Formato ---
function fmtUsd(v) {
    if (v == null) return '—';
    return 'USD ' + Number(v).toLocaleString('es-BO', { maximumFractionDigits: 0 });
}
function fmtVal(v) {
    if (v == null) return '—';
    const u = ranking.value?.unidad === 'kg' ? 'kg' : 'USD';
    return `${Number(v).toLocaleString('es-BO', { maximumFractionDigits: 0 })} ${u}`;
}
function fmtPct(v) {
    return v == null ? '—' : `${Number(v).toLocaleString('es-BO', { maximumFractionDigits: 1 })}%`;
}
function fmtVarPct(v) {
    if (v == null) return '—';
    const s = v > 0 ? '+' : '';
    return `${s}${Number(v).toLocaleString('es-BO', { maximumFractionDigits: 1 })}%`;
}

// --- Grafico de barras horizontales del ranking ---
const serieRanking = computed(() => [{
    name: ranking.value?.metrica === 'peso' ? 'Peso' : 'Valor',
    data: (ranking.value?.filas ?? []).map((r) => Math.round(r.valor)),
}]);
const opcRanking = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false } },
    plotOptions: { bar: { horizontal: true, borderRadius: 3, barHeight: '65%' } },
    colors: [f.flujo === 1 ? '#16a34a' : '#dc2626'],
    dataLabels: { enabled: false },
    xaxis: {
        categories: (ranking.value?.filas ?? []).map((r) => r.label),
        labels: { formatter: (v) => Number(v).toLocaleString('es-BO', { notation: 'compact' }) },
    },
    yaxis: { labels: { maxWidth: 200, style: { fontSize: '11px' } } },
    tooltip: { y: { formatter: (v) => fmtVal(v) } },
    grid: { strokeDashArray: 3 },
}));

const gestionesDesc = computed(() => [...props.gestiones]);
</script>

<template>
    <Head title="Rankings y comparadores" />

    <section class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
        <h1 class="text-2xl sm:text-3xl font-bold text-slate-800">Rankings y comparadores</h1>
        <p class="text-slate-500 mt-1">Explora quien lidera el comercio exterior y compara periodos.</p>

        <!-- Pestanias -->
        <div class="mt-5 flex gap-2 border-b border-slate-200">
            <button
                v-for="t in [{k:'ranking',l:'Rankings'},{k:'comparador',l:'Comparadores'}]"
                :key="t.k"
                @click="tab = t.k"
                class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition"
                :class="tab === t.k ? 'border-marca-600 text-marca-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
            >
                {{ t.l }}
            </button>
        </div>

        <!-- ============ RANKINGS ============ -->
        <div v-show="tab === 'ranking'" class="mt-6">
            <!-- Filtros -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 grid grid-cols-2 md:grid-cols-6 gap-3">
                <label class="text-xs text-slate-500">Organizacion
                    <select v-model="f.organizacion_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                        <option v-for="o in organizaciones" :key="o.organizacion_id" :value="o.organizacion_id">{{ o.sigla || o.nombre }}</option>
                    </select>
                </label>
                <label class="text-xs text-slate-500">Gestion
                    <select v-model="f.gestion" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                        <option v-for="g in gestionesDesc" :key="g" :value="g">{{ g }}</option>
                    </select>
                </label>
                <label class="text-xs text-slate-500">Flujo
                    <select v-model.number="f.flujo" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                        <option :value="1">Exportacion</option>
                        <option :value="2">Importacion</option>
                    </select>
                </label>
                <label class="text-xs text-slate-500">Dimension
                    <select v-model="f.dimension" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                        <option value="producto">Productos</option>
                        <option value="pais">Paises</option>
                        <option value="departamento">Departamentos</option>
                    </select>
                </label>
                <label class="text-xs text-slate-500">Medir por
                    <select v-model="f.metrica" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                        <option value="valor">Valor (USD)</option>
                        <option value="peso">Volumen (kg)</option>
                    </select>
                </label>
                <label class="text-xs text-slate-500">Posiciones
                    <select v-model.number="f.limite" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                        <option :value="10">Top 10</option>
                        <option :value="20">Top 20</option>
                        <option :value="50">Top 50</option>
                    </select>
                </label>
            </div>

            <div v-if="ranking" class="mt-5 grid grid-cols-1 lg:grid-cols-2 gap-5" :class="{ 'opacity-60': cargandoR }">
                <!-- Tabla -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-semibold text-slate-800 text-sm">{{ ranking.titulo }}</h3>
                        <div class="flex gap-2">
                            <button @click="exportar('xlsx')" class="text-xs px-2.5 py-1.5 rounded bg-green-50 text-green-700 hover:bg-green-100 font-medium">Excel</button>
                            <button @click="exportar('csv')" class="text-xs px-2.5 py-1.5 rounded bg-slate-100 text-slate-700 hover:bg-slate-200 font-medium">CSV</button>
                        </div>
                    </div>
                    <table class="w-full text-sm">
                        <thead class="text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="text-left py-2 w-8">#</th>
                                <th class="text-left py-2">Nombre</th>
                                <th class="text-right py-2">{{ ranking.unidad === 'kg' ? 'Peso' : 'Valor' }}</th>
                                <th class="text-right py-2">% total</th>
                                <th class="text-right py-2">% acum.</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="r in ranking.filas" :key="r.posicion" class="hover:bg-slate-50">
                                <td class="py-2 text-slate-400">{{ r.posicion }}</td>
                                <td class="py-2 text-slate-800">{{ r.label }}</td>
                                <td class="py-2 text-right font-medium">{{ fmtVal(r.valor) }}</td>
                                <td class="py-2 text-right text-slate-600">{{ fmtPct(r.porcentaje) }}</td>
                                <td class="py-2 text-right text-slate-400">{{ fmtPct(r.acumulado) }}</td>
                            </tr>
                            <tr v-if="!ranking.filas.length"><td colspan="5" class="py-8 text-center text-slate-400">Sin datos para estos filtros.</td></tr>
                        </tbody>
                    </table>
                </div>

                <!-- Grafico -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                    <h3 class="font-semibold text-slate-800 text-sm mb-2">Grafico</h3>
                    <apexchart v-if="ranking.filas.length" type="bar" :height="Math.max(260, ranking.filas.length * 28)" :options="opcRanking" :series="serieRanking" />
                    <p v-else class="text-sm text-slate-400 py-8 text-center">Sin datos.</p>
                </div>
            </div>
        </div>

        <!-- ============ COMPARADORES ============ -->
        <div v-show="tab === 'comparador'" class="mt-6">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 grid grid-cols-2 md:grid-cols-6 gap-3">
                <label class="text-xs text-slate-500">Comparar
                    <select v-model="c.modo" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                        <option value="anios">Dos anios</option>
                        <option value="flujos">Expo vs Impo</option>
                    </select>
                </label>
                <label class="text-xs text-slate-500">Dimension
                    <select v-model="c.dimension" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                        <option value="producto">Productos</option>
                        <option value="pais">Paises</option>
                    </select>
                </label>
                <template v-if="c.modo === 'anios'">
                    <label class="text-xs text-slate-500">Flujo
                        <select v-model.number="c.flujo" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                            <option :value="1">Exportacion</option>
                            <option :value="2">Importacion</option>
                        </select>
                    </label>
                    <label class="text-xs text-slate-500">Anio A
                        <select v-model="c.anio_a" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                            <option v-for="g in gestionesDesc" :key="g" :value="g">{{ g }}</option>
                        </select>
                    </label>
                    <label class="text-xs text-slate-500">Anio B
                        <select v-model="c.anio_b" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                            <option v-for="g in gestionesDesc" :key="g" :value="g">{{ g }}</option>
                        </select>
                    </label>
                </template>
                <template v-else>
                    <label class="text-xs text-slate-500">Gestion
                        <select v-model="c.gestion" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                            <option v-for="g in gestionesDesc" :key="g" :value="g">{{ g }}</option>
                        </select>
                    </label>
                </template>
                <label class="text-xs text-slate-500">Posiciones
                    <select v-model.number="c.limite" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                        <option :value="10">Top 10</option>
                        <option :value="20">Top 20</option>
                        <option :value="50">Top 50</option>
                    </select>
                </label>
                <div class="flex items-end">
                    <button @click="cargarComparador" class="w-full px-3 py-2 rounded-lg bg-marca-700 text-white text-sm font-medium hover:bg-marca-800">Comparar</button>
                </div>
            </div>

            <div v-if="comparador" class="mt-5 bg-white rounded-xl border border-slate-200 shadow-sm p-5" :class="{ 'opacity-60': cargandoC }">
                <h3 class="font-semibold text-slate-800 text-sm mb-3">{{ comparador.titulo }}</h3>

                <!-- Comparar dos anios -->
                <table v-if="c.modo === 'anios'" class="w-full text-sm">
                    <thead class="text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="text-left py-2">Nombre</th>
                            <th class="text-right py-2">{{ comparador.anio_a }}</th>
                            <th class="text-right py-2">{{ comparador.anio_b }}</th>
                            <th class="text-right py-2">Variacion</th>
                            <th class="text-right py-2">%</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(r, i) in comparador.filas" :key="i" class="hover:bg-slate-50">
                            <td class="py-2 text-slate-800">{{ r.label }}</td>
                            <td class="py-2 text-right text-slate-600">{{ fmtUsd(r.valor_a) }}</td>
                            <td class="py-2 text-right text-slate-600">{{ fmtUsd(r.valor_b) }}</td>
                            <td class="py-2 text-right font-medium" :class="r.variacion >= 0 ? 'text-green-600' : 'text-red-600'">{{ fmtUsd(r.variacion) }}</td>
                            <td class="py-2 text-right" :class="(r.variacion_pct ?? 0) >= 0 ? 'text-green-600' : 'text-red-600'">{{ fmtVarPct(r.variacion_pct) }}</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Comparar expo vs impo -->
                <table v-else class="w-full text-sm">
                    <thead class="text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="text-left py-2">Nombre</th>
                            <th class="text-right py-2">Exportacion</th>
                            <th class="text-right py-2">Importacion</th>
                            <th class="text-right py-2">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(r, i) in comparador.filas" :key="i" class="hover:bg-slate-50">
                            <td class="py-2 text-slate-800">{{ r.label }}</td>
                            <td class="py-2 text-right text-slate-600">{{ fmtUsd(r.expo) }}</td>
                            <td class="py-2 text-right text-slate-600">{{ fmtUsd(r.impo) }}</td>
                            <td class="py-2 text-right font-medium" :class="r.balance >= 0 ? 'text-green-600' : 'text-red-600'">{{ fmtUsd(r.balance) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="text-xs text-slate-400 mt-6">Fuente: INE - Bolivia. Los porcentajes del ranking se calculan sobre el total general de la dimension.</p>
    </section>
</template>
