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

// Cada organización tiene su propio rango de años con datos (INE llega a 2026,
// ALADI a 2025, etc.): la lista de gestiones se pide por organización y al
// cambiar de organización se salta a su gestión más reciente.
const gestionesOrg = ref([...props.gestiones]);

async function cargarGestiones() {
    try {
        const { data } = await axios.get('/api/v1/filtros/gestiones', { params: { org: f.organizacion_id } });
        const lista = data?.data ?? [];
        gestionesOrg.value = lista.length ? lista : [...props.gestiones];
    } catch {
        gestionesOrg.value = [...props.gestiones];
    }
    const g = gestionesOrg.value;
    if (!g.includes(f.gestion)) f.gestion = g[0] ?? null;
    if (!g.includes(c.gestion)) c.gestion = g[0] ?? null;
    if (!g.includes(c.anio_b)) c.anio_b = g[0] ?? null;
    if (!g.includes(c.anio_a)) c.anio_a = g[1] ?? g[0] ?? null;
}

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
    modo: 'anios', // 'años' | 'flujos'
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
watch([() => f.gestion, () => f.flujo, () => f.dimension, () => f.metrica, () => f.limite], cargarRanking);
// Al cambiar la organización: sincronizar el comparador, ajustar las gestiones
// disponibles y recargar (si la gestión cambio, el watch de arriba ya recarga).
watch(() => f.organizacion_id, async () => {
    c.organizacion_id = f.organizacion_id;
    const antes = f.gestion;
    await cargarGestiones();
    if (f.gestion === antes) cargarRanking();
    comparador.value = null;
});
watch(tab, (t) => { if (t === 'comparador' && !comparador.value) cargarComparador(); });

// --- Formato ---
function fmtUsd(v) {
    if (v == null) return '—';
    return 'USD ' + Number(v).toLocaleString('es-BO', { maximumFractionDigits: 0 });
}
function fmtVal(v) {
    if (v == null) return '—';
    const unidad = ranking.value?.unidad;
    // FAOSTAT publica índices (2014-2016 = 100), no montos.
    if (unidad === 'índice') return Number(v).toLocaleString('es-BO', { maximumFractionDigits: 1 });
    return `${Number(v).toLocaleString('es-BO', { maximumFractionDigits: 0 })} ${unidad === 'kg' ? 'kg' : 'USD'}`;
}
function fmtPct(v) {
    return v == null ? '—' : `${Number(v).toLocaleString('es-BO', { maximumFractionDigits: 1 })}%`;
}
function fmtVarPct(v) {
    if (v == null) return '—';
    const s = v > 0 ? '+' : '';
    return `${s}${Number(v).toLocaleString('es-BO', { maximumFractionDigits: 1 })}%`;
}

// --- Gráfico de barras horizontales del ranking ---
// Barras en azul institucional; el primer puesto (mayor) resaltado en rojo.
const serieRanking = computed(() => [{
    name: ranking.value?.metrica === 'peso' ? 'Peso' : 'Valor',
    data: (ranking.value?.filas ?? []).map((r) => Math.round(r.valor)),
}]);
const opcRanking = computed(() => {
    const filas = ranking.value?.filas ?? [];
    return {
        chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'Plus Jakarta Sans, sans-serif' },
        plotOptions: { bar: { horizontal: true, borderRadius: 6, borderRadiusApplication: 'end', barHeight: '46%', distributed: true } },
        colors: filas.map((_, i) => (i === 0 ? '#e11d48' : '#334155')),
        dataLabels: { enabled: false },
        legend: { show: false },
        xaxis: {
            categories: filas.map((r) => r.label),
            labels: { formatter: (v) => Number(v).toLocaleString('es-BO', { notation: 'compact' }), style: { colors: '#94a3b8' } },
            axisBorder: { show: false }, axisTicks: { show: false },
        },
        yaxis: { labels: { maxWidth: 200, style: { fontSize: '11px', colors: '#475569' } } },
        tooltip: { y: { formatter: (v) => fmtVal(v) } },
        grid: { strokeDashArray: 4, borderColor: '#f1f5f9', yaxis: { lines: { show: false } } },
    };
});

const gestionesDesc = computed(() => [...gestionesOrg.value]);
const orgActual = computed(() => props.organizaciones.find((o) => o.organizacion_id === f.organizacion_id));
</script>

<template>
    <Head title="Rankings y comparadores" />

    <!-- Encabezado luminoso -->
    <section class="bg-white border-b border-gris-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <p class="inline-flex items-center gap-2.5 text-[11px] font-bold uppercase tracking-[0.18em] text-rojo-600 mb-4">
                <span class="w-7 h-px bg-rojo-500"></span> Rankings
            </p>
            <h1 class="titular-editorial text-4xl sm:text-5xl text-institucional-900">Quién lidera el comercio exterior</h1>
            <p class="text-institucional-500 mt-4 max-w-xl leading-relaxed text-lg">Rankings por valor o volumen y comparadores entre periodos.</p>
        </div>
    </section>

    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <!-- Pestanias -->
        <div class="flex gap-2 border-b border-gris-200">
            <button
                v-for="t in [{k:'ranking',l:'Rankings'},{k:'comparador',l:'Comparadores'}]"
                :key="t.k"
                @click="tab = t.k"
                class="relative px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition"
                :class="tab === t.k ? 'border-rojo-600 text-institucional-900' : 'border-transparent text-gris-500 hover:text-institucional-900'"
            >
                {{ t.l }}
            </button>
        </div>

        <!-- ============ RANKINGS ============ -->
        <div v-show="tab === 'ranking'" class="mt-6">
            <!-- Filtros -->
            <div class="tarjeta p-4 grid grid-cols-2 md:grid-cols-6 gap-3">
                <label class="text-xs font-medium text-gris-500">Organización
                    <select v-model="f.organizacion_id" class="mt-1 w-full rounded-lg border-gris-300 text-sm focus:ring-2 focus:ring-institucional-400">
                        <option v-for="o in organizaciones" :key="o.organizacion_id" :value="o.organizacion_id">{{ o.sigla || o.nombre }}</option>
                    </select>
                </label>
                <label class="text-xs font-medium text-gris-500">Gestión
                    <select v-model="f.gestion" class="mt-1 w-full rounded-lg border-gris-300 text-sm focus:ring-2 focus:ring-institucional-400">
                        <option v-for="g in gestionesDesc" :key="g" :value="g">{{ g }}</option>
                    </select>
                </label>
                <label class="text-xs font-medium text-gris-500">Flujo
                    <select v-model.number="f.flujo" class="mt-1 w-full rounded-lg border-gris-300 text-sm focus:ring-2 focus:ring-institucional-400">
                        <option :value="1">Exportación</option>
                        <option :value="2">Importación</option>
                    </select>
                </label>
                <label class="text-xs font-medium text-gris-500">Dimensión
                    <select v-model="f.dimension" class="mt-1 w-full rounded-lg border-gris-300 text-sm focus:ring-2 focus:ring-institucional-400">
                        <option value="producto">Productos</option>
                        <option value="pais">Países</option>
                        <option value="departamento">Departamentos</option>
                    </select>
                </label>
                <label class="text-xs font-medium text-gris-500">Medir por
                    <select v-model="f.metrica" class="mt-1 w-full rounded-lg border-gris-300 text-sm focus:ring-2 focus:ring-institucional-400">
                        <option value="valor">Valor (USD)</option>
                        <option value="peso">Volumen (kg)</option>
                    </select>
                </label>
                <label class="text-xs font-medium text-gris-500">Posiciones
                    <select v-model.number="f.limite" class="mt-1 w-full rounded-lg border-gris-300 text-sm focus:ring-2 focus:ring-institucional-400">
                        <option :value="10">Top 10</option>
                        <option :value="20">Top 20</option>
                        <option :value="50">Top 50</option>
                    </select>
                </label>
            </div>

            <div v-if="ranking" class="mt-5 grid grid-cols-1 lg:grid-cols-2 gap-5" :class="{ 'opacity-60': cargandoR }">
                <!-- Tabla -->
                <div class="tarjeta p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-display font-bold text-institucional-900">{{ ranking.titulo }}</h3>
                        <div class="flex gap-2">
                            <button @click="exportar('xlsx')" class="text-xs px-2.5 py-1.5 rounded bg-positivo-suave text-positivo hover:opacity-80 font-semibold">Excel</button>
                            <button @click="exportar('csv')" class="text-xs px-2.5 py-1.5 rounded bg-gris-100 text-gris-700 hover:bg-gris-200 font-semibold">CSV</button>
                        </div>
                    </div>
                    <table class="w-full text-sm">
                        <thead class="text-xs font-semibold text-institucional-500 uppercase tracking-wider border-b border-gris-200">
                            <tr>
                                <th class="text-left py-2 w-8">#</th>
                                <th class="text-left py-2">Nombre</th>
                                <th class="text-right py-2">{{ ranking.unidad === 'kg' ? 'Peso' : 'Valor' }}</th>
                                <th class="text-right py-2">% total</th>
                                <th class="text-right py-2">% acum.</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gris-100">
                            <tr v-for="r in ranking.filas" :key="r.posicion"
                                class="transition-all duration-200 ease-out"
                                :class="r.posicion === 1 ? 'bg-rojo-50' : 'hover:bg-gris-50'">
                                <td class="py-2">
                                    <span v-if="r.posicion === 1" class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-rojo-600 text-white text-xs font-bold">1</span>
                                    <span v-else class="text-gris-400 pl-1.5">{{ r.posicion }}</span>
                                </td>
                                <td class="py-2 font-medium" :class="r.posicion === 1 ? 'text-institucional-900' : 'text-gris-800'">{{ r.label }}</td>
                                <td class="py-2 text-right font-semibold text-institucional-900">{{ fmtVal(r.valor) }}</td>
                                <td class="py-2 text-right text-gris-600">{{ fmtPct(r.porcentaje) }}</td>
                                <td class="py-2 text-right text-gris-400">{{ fmtPct(r.acumulado) }}</td>
                            </tr>
                            <tr v-if="!ranking.filas.length"><td colspan="5" class="py-8 text-center text-gris-400">Sin datos para estos filtros.</td></tr>
                        </tbody>
                    </table>
                </div>

                <!-- Gráfico -->
                <div class="tarjeta p-5">
                    <h3 class="font-display font-bold text-institucional-900 mb-2">Gráfico</h3>
                    <apexchart v-if="ranking.filas.length" type="bar" :height="Math.max(260, ranking.filas.length * 28)" :options="opcRanking" :series="serieRanking" />
                    <p v-else class="text-sm text-gris-400 py-8 text-center">Sin datos.</p>
                </div>
            </div>
        </div>

        <!-- ============ COMPARADORES ============ -->
        <div v-show="tab === 'comparador'" class="mt-6">
            <div class="tarjeta p-4 grid grid-cols-2 md:grid-cols-7 gap-3">
                <label class="text-xs font-medium text-gris-500">Organización
                    <select v-model="f.organizacion_id" class="mt-1 w-full rounded-lg border-gris-300 text-sm focus:ring-2 focus:ring-institucional-400">
                        <option v-for="o in organizaciones" :key="o.organizacion_id" :value="o.organizacion_id">{{ o.sigla || o.nombre }}</option>
                    </select>
                </label>
                <label class="text-xs font-medium text-gris-500">Comparar
                    <select v-model="c.modo" class="mt-1 w-full rounded-lg border-gris-300 text-sm focus:ring-2 focus:ring-institucional-400">
                        <option value="anios">Dos años</option>
                        <option value="flujos">Expo vs Impo</option>
                    </select>
                </label>
                <label class="text-xs font-medium text-gris-500">Dimensión
                    <select v-model="c.dimension" class="mt-1 w-full rounded-lg border-gris-300 text-sm focus:ring-2 focus:ring-institucional-400">
                        <option value="producto">Productos</option>
                        <option value="pais">Países</option>
                    </select>
                </label>
                <template v-if="c.modo === 'anios'">
                    <label class="text-xs font-medium text-gris-500">Flujo
                        <select v-model.number="c.flujo" class="mt-1 w-full rounded-lg border-gris-300 text-sm focus:ring-2 focus:ring-institucional-400">
                            <option :value="1">Exportación</option>
                            <option :value="2">Importación</option>
                        </select>
                    </label>
                    <label class="text-xs font-medium text-gris-500">Año A
                        <select v-model="c.anio_a" class="mt-1 w-full rounded-lg border-gris-300 text-sm focus:ring-2 focus:ring-institucional-400">
                            <option v-for="g in gestionesDesc" :key="g" :value="g">{{ g }}</option>
                        </select>
                    </label>
                    <label class="text-xs font-medium text-gris-500">Año B
                        <select v-model="c.anio_b" class="mt-1 w-full rounded-lg border-gris-300 text-sm focus:ring-2 focus:ring-institucional-400">
                            <option v-for="g in gestionesDesc" :key="g" :value="g">{{ g }}</option>
                        </select>
                    </label>
                </template>
                <template v-else>
                    <label class="text-xs font-medium text-gris-500">Gestión
                        <select v-model="c.gestion" class="mt-1 w-full rounded-lg border-gris-300 text-sm focus:ring-2 focus:ring-institucional-400">
                            <option v-for="g in gestionesDesc" :key="g" :value="g">{{ g }}</option>
                        </select>
                    </label>
                </template>
                <label class="text-xs font-medium text-gris-500">Posiciones
                    <select v-model.number="c.limite" class="mt-1 w-full rounded-lg border-gris-300 text-sm focus:ring-2 focus:ring-institucional-400">
                        <option :value="10">Top 10</option>
                        <option :value="20">Top 20</option>
                        <option :value="50">Top 50</option>
                    </select>
                </label>
                <div class="flex items-end">
                    <button @click="cargarComparador" class="btn btn-secundario w-full">Comparar</button>
                </div>
            </div>

            <div v-if="comparador" class="mt-5 tarjeta p-5" :class="{ 'opacity-60': cargandoC }">
                <h3 class="font-display font-bold text-institucional-900 mb-3">{{ comparador.titulo }}</h3>

                <!-- Comparar dos años -->
                <table v-if="c.modo === 'anios'" class="w-full text-sm">
                    <thead class="text-xs font-semibold text-institucional-500 uppercase tracking-wider border-b border-gris-200">
                        <tr>
                            <th class="text-left py-2">Nombre</th>
                            <th class="text-right py-2">{{ comparador.anio_a }}</th>
                            <th class="text-right py-2">{{ comparador.anio_b }}</th>
                            <th class="text-right py-2">Variación</th>
                            <th class="text-right py-2">%</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gris-100">
                        <tr v-for="(r, i) in comparador.filas" :key="i" class="hover:bg-gris-50 transition-all duration-200 ease-out">
                            <td class="py-2 text-gris-800 font-medium">{{ r.label }}</td>
                            <td class="py-2 text-right text-gris-600">{{ fmtUsd(r.valor_a) }}</td>
                            <td class="py-2 text-right text-gris-600">{{ fmtUsd(r.valor_b) }}</td>
                            <td class="py-2 text-right font-semibold" :class="r.variacion >= 0 ? 'text-positivo' : 'text-negativo'">{{ fmtUsd(r.variacion) }}</td>
                            <td class="py-2 text-right font-medium" :class="(r.variacion_pct ?? 0) >= 0 ? 'text-positivo' : 'text-negativo'">{{ fmtVarPct(r.variacion_pct) }}</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Comparar expo vs impo -->
                <table v-else class="w-full text-sm">
                    <thead class="text-xs font-semibold text-institucional-500 uppercase tracking-wider border-b border-gris-200">
                        <tr>
                            <th class="text-left py-2">Nombre</th>
                            <th class="text-right py-2">Exportación</th>
                            <th class="text-right py-2">Importación</th>
                            <th class="text-right py-2">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gris-100">
                        <tr v-for="(r, i) in comparador.filas" :key="i" class="hover:bg-gris-50 transition-all duration-200 ease-out">
                            <td class="py-2 text-gris-800 font-medium">{{ r.label }}</td>
                            <td class="py-2 text-right text-gris-600">{{ fmtUsd(r.expo) }}</td>
                            <td class="py-2 text-right text-gris-600">{{ fmtUsd(r.impo) }}</td>
                            <td class="py-2 text-right font-semibold" :class="r.balance >= 0 ? 'text-positivo' : 'text-negativo'">{{ fmtUsd(r.balance) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="text-xs text-gris-400 mt-6">Fuente: {{ orgActual?.sigla || 'INE' }}. Los porcentajes del ranking se calculan sobre el total general de la dimension.</p>
    </section>
</template>
