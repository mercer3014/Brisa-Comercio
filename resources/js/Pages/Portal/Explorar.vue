<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import FacetaFiltro from '@/Components/FacetaFiltro.vue';

const props = defineProps({
    organizacionDefecto: { type: Number, default: 1 },
    opciones: { type: Object, default: () => ({}) },
});

const orgId = ref(props.organizacionDefecto);
const busqueda = ref('');
const pagina = ref(1);
const porPagina = ref(25);
const cargando = ref(false);
const filtrosAbiertos = ref(false); // panel movil

const filtros = reactive({
    flujo: [], tipo_operacion: [], gestion: [], mes: [], pais: [], zona: [], departamento: [],
    medio: [], via: [], seccion: [], capitulo: [], cuci: [], ciiu: [], gce: [], tnt: [], cuode: [],
});

const totales = ref({ total: 0, valor: 0, peso: 0 });
const tabla = ref({ data: [], total: 0, pagina: 1, ultima_pagina: 1 });
const facetas = ref({});
const graficos = ref({ top_paises: [], top_productos: [] });

const panel = [
    { key: 'flujo', titulo: 'Flujo' },
    { key: 'tipo_operacion', titulo: 'Tipo de operación' },
    { key: 'gestion', titulo: 'Gestión (año)' },
    { key: 'mes', titulo: 'Mes' },
    { key: 'pais', titulo: 'País' },
    { key: 'zona', titulo: 'Zona' },
    { key: 'departamento', titulo: 'Departamento' },
    { key: 'medio', titulo: 'Medio de transporte' },
    { key: 'via', titulo: 'Vía' },
    { key: 'seccion', titulo: 'Sección NANDINA' },
    { key: 'capitulo', titulo: 'Capítulo NANDINA' },
    { key: 'cuci', titulo: 'CUCI' },
    { key: 'ciiu', titulo: 'CIIU' },
    { key: 'gce', titulo: 'GCE' },
    { key: 'tnt', titulo: 'TNT' },
    { key: 'cuode', titulo: 'CUODE' },
];

// --- Sincronizacion con la URL (para compartir el enlace) ---
function sincronizarUrl() {
    const p = new URLSearchParams();
    p.set('org', orgId.value);
    if (busqueda.value) p.set('q', busqueda.value);
    for (const k in filtros) {
        if (filtros[k].length) p.set(k, filtros[k].join(','));
    }
    const qs = p.toString();
    window.history.replaceState(null, '', qs ? `?${qs}` : window.location.pathname);
}

function leerUrl() {
    const p = new URLSearchParams(window.location.search);
    if (p.get('org')) orgId.value = Number(p.get('org'));
    if (p.get('q')) busqueda.value = p.get('q');
    for (const k in filtros) {
        const v = p.get(k);
        if (v) filtros[k] = v.split(',').map(Number).filter((n) => !isNaN(n));
    }
}

let debounce = null;
async function consultar() {
    cargando.value = true;
    sincronizarUrl();
    try {
        const { data } = await axios.post('/explorar/consultar', {
            organizacion_id: orgId.value,
            pagina: pagina.value,
            por_pagina: porPagina.value,
            filtros: { ...filtros, busqueda: busqueda.value },
        });
        totales.value = data.totales;
        tabla.value = data.tabla;
        facetas.value = data.facetas;
        graficos.value = data.graficos;
    } finally {
        cargando.value = false;
    }
}

function consultarDesdeFiltro() {
    pagina.value = 1;
    consultar();
}

watch(filtros, consultarDesdeFiltro, { deep: true });
watch(busqueda, () => {
    clearTimeout(debounce);
    debounce = setTimeout(consultarDesdeFiltro, 350);
});
watch(orgId, consultarDesdeFiltro);
watch(pagina, consultar);

function limpiarTodo() {
    Object.keys(filtros).forEach((k) => (filtros[k] = []));
    busqueda.value = '';
}

function exportar(formato) {
    const p = new URLSearchParams();
    p.set('organizacion_id', orgId.value);
    p.set('formato', formato);
    for (const k in filtros) {
        filtros[k].forEach((v) => p.append(`filtros[${k}][]`, v));
    }
    if (busqueda.value) p.set('filtros[busqueda]', busqueda.value);
    window.location.href = `/explorar/exportar?${p.toString()}`;
}

const fmt = (n) => new Intl.NumberFormat('es-BO', { maximumFractionDigits: 0 }).format(n || 0);
const fmtUsd = (n) => '$ ' + new Intl.NumberFormat('es-BO', { maximumFractionDigits: 0 }).format(n || 0);
// Cifras grandes de las tarjetas: siempre cortas para que no se salgan del
// cuadro (B = billones, mil M = miles de millones, M = millones).
const fmtCorto = (n) => {
    const v = n || 0;
    const abs = Math.abs(v);
    const nf = (x, d) => new Intl.NumberFormat('es-BO', { maximumFractionDigits: d }).format(x);
    if (abs >= 1e12) return nf(v / 1e12, 2) + ' B';
    if (abs >= 1e9) return nf(v / 1e9, 1) + ' mil M';
    if (abs >= 1e6) return nf(v / 1e6, 1) + ' M';
    return nf(v, 0);
};

// Gráficos (barras horizontales estilizadas) — navy slate general, Top 1 crimson.
function opcBarras(items) {
    return {
        chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'Plus Jakarta Sans, sans-serif' },
        plotOptions: { bar: { horizontal: true, borderRadius: 6, borderRadiusApplication: 'end', barHeight: '46%', distributed: true } },
        colors: items.map((_, i) => (i === 0 ? '#e11d48' : '#334155')),
        dataLabels: { enabled: false },
        legend: { show: false },
        xaxis: {
            categories: items.map((i) => i.label),
            labels: { formatter: (v) => Number(v).toLocaleString('es-BO', { notation: 'compact' }), style: { colors: '#94a3b8' } },
            axisBorder: { show: false }, axisTicks: { show: false },
        },
        yaxis: { labels: { maxWidth: 150, style: { fontSize: '10px', colors: '#475569' } } },
        tooltip: { y: { formatter: (v) => fmtUsd(v) } },
        grid: { strokeDashArray: 4, borderColor: '#f1f5f9', yaxis: { lines: { show: false } } },
    };
}
const seriePaises = computed(() => [{ name: 'Valor', data: graficos.value.top_paises.map((i) => Math.round(i.valor)) }]);
const serieProductos = computed(() => [{ name: 'Valor', data: graficos.value.top_productos.map((i) => Math.round(i.valor)) }]);
const opcPaises = computed(() => opcBarras(graficos.value.top_paises));
const opcProductos = computed(() => opcBarras(graficos.value.top_productos));

onMounted(() => {
    leerUrl();
    consultar();
});
</script>

<template>
    <Head title="Explorar datos" />

    <!-- Encabezado luminoso -->
    <section class="bg-white border-b border-gris-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <p class="inline-flex items-center gap-2.5 text-[11px] font-bold uppercase tracking-[0.18em] text-rojo-600 mb-4">
                <span class="w-7 h-px bg-rojo-500"></span> Explorador
            </p>
            <h1 class="titular-editorial text-4xl sm:text-5xl text-institucional-900">Explora el detalle de las operaciones</h1>
            <p class="text-institucional-500 mt-4 max-w-xl leading-relaxed text-lg">Filtra a gusto y descarga el resultado. Fuente: INE — Bolivia.</p>
        </div>
    </section>

    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="flex items-center justify-end">
            <button
                @click="filtrosAbiertos = !filtrosAbiertos"
                class="lg:hidden btn btn-contorno"
            >
                {{ filtrosAbiertos ? 'Ocultar filtros' : 'Mostrar filtros' }}
            </button>
        </div>

        <div class="mt-3 flex flex-col lg:flex-row gap-5">
            <!-- Panel de filtros (colapsable en movil) -->
            <aside
                class="w-full lg:w-72 shrink-0 tarjeta flex flex-col overflow-hidden"
                :class="{ 'hidden lg:flex': !filtrosAbiertos }"
            >
                <div class="px-4 py-3.5 border-b border-gris-100 flex items-center justify-between">
                    <h2 class="font-bold text-sm text-institucional-900 tracking-tight">Filtros</h2>
                    <button @click="limpiarTodo" class="text-xs font-semibold text-rojo-600 hover:text-rojo-700 transition-colors">Limpiar todo</button>
                </div>
                <div class="p-3 border-b border-gris-100">
                    <label class="block text-xs font-semibold text-institucional-400 uppercase tracking-wide mb-1.5">Organización</label>
                    <select v-model="orgId" class="campo">
                        <option v-for="o in opciones.organizaciones" :key="o.organizacion_id" :value="o.organizacion_id">{{ o.nombre }}</option>
                    </select>
                </div>
                <div class="lg:max-h-[60vh] overflow-y-auto">
                    <FacetaFiltro
                        v-for="p in panel" :key="p.key"
                        :titulo="p.titulo"
                        :opciones="opciones[p.key] || []"
                        :conteos="facetas[p.key] || {}"
                        v-model="filtros[p.key]"
                    />
                </div>
            </aside>

            <!-- Resultados -->
            <main class="flex-1 min-w-0">
                <!-- Búsqueda -->
                <input v-model="busqueda" placeholder="Buscar por producto, país o aduana..."
                       class="campo px-4 py-3 mb-4" />

                <!-- Resumen visual: KPIs -->
                <div class="grid grid-cols-3 gap-3 mb-4" :class="{ 'opacity-60': cargando }">
                    <div class="tarjeta p-4">
                        <div class="text-xs text-gris-500 uppercase tracking-wide">Operaciones</div>
                        <div class="text-xl sm:text-2xl font-bold text-institucional-900 mt-1">{{ fmt(totales.total) }}</div>
                    </div>
                    <div class="tarjeta p-4">
                        <div class="text-xs text-gris-500 uppercase tracking-wide">{{ totales.etiqueta_valor || 'Valor total' }}</div>
                        <div class="text-xl sm:text-2xl font-bold text-institucional-900 mt-1 whitespace-nowrap">{{ totales.etiqueta_valor ? fmt(totales.valor) : '$ ' + fmtCorto(totales.valor) }}</div>
                    </div>
                    <div class="tarjeta p-4">
                        <div class="text-xs text-gris-500 uppercase tracking-wide">{{ totales.etiqueta_peso || 'Peso bruto (kg)' }}</div>
                        <div class="text-xl sm:text-2xl font-bold text-institucional-900 mt-1 whitespace-nowrap">{{ fmtCorto(totales.peso) }}</div>
                    </div>
                </div>

                <!-- Resumen visual: gráficos del subconjunto -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="tarjeta p-4">
                        <h3 class="text-sm font-semibold text-institucional-900 mb-1">Top países (del filtro)</h3>
                        <apexchart v-if="seriePaises[0].data.length" type="bar" height="240" :options="opcPaises" :series="seriePaises" />
                        <p v-else class="text-xs text-gris-400 py-8 text-center">Sin datos.</p>
                    </div>
                    <div class="tarjeta p-4">
                        <h3 class="text-sm font-semibold text-institucional-900 mb-1">Top productos (del filtro)</h3>
                        <apexchart v-if="serieProductos[0].data.length" type="bar" height="240" :options="opcProductos" :series="serieProductos" />
                        <p v-else class="text-xs text-gris-400 py-8 text-center">Sin datos.</p>
                    </div>
                </div>

                <!-- Tabla detallada -->
                <div class="tarjeta overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-2.5 border-b border-gris-100">
                        <span class="text-sm font-semibold text-institucional-900">Detalle</span>
                        <div class="flex gap-2">
                            <button @click="exportar('xlsx')" class="text-xs px-2.5 py-1.5 rounded bg-positivo-suave text-positivo hover:opacity-80 font-semibold">Excel</button>
                            <button @click="exportar('csv')" class="text-xs px-2.5 py-1.5 rounded bg-gris-100 text-gris-700 hover:bg-gris-200 font-semibold">CSV</button>
                        </div>
                    </div>
                    <div class="overflow-auto max-h-[60vh]">
                        <table class="w-full text-sm">
                            <thead class="bg-gris-50 sticky top-0 border-b border-gris-200">
                                <tr>
                                    <th class="text-left px-3 py-3 text-xs font-semibold text-institucional-500 uppercase tracking-wider">Gestión</th>
                                    <th class="text-left px-3 py-3 text-xs font-semibold text-institucional-500 uppercase tracking-wider">Mes</th>
                                    <th class="text-left px-3 py-3 text-xs font-semibold text-institucional-500 uppercase tracking-wider">Tipo</th>
                                    <th class="text-left px-3 py-3 text-xs font-semibold text-institucional-500 uppercase tracking-wider">NANDINA</th>
                                    <th class="text-left px-3 py-3 text-xs font-semibold text-institucional-500 uppercase tracking-wider">Producto</th>
                                    <th class="text-left px-3 py-3 text-xs font-semibold text-institucional-500 uppercase tracking-wider">País</th>
                                    <th class="text-left px-3 py-3 text-xs font-semibold text-institucional-500 uppercase tracking-wider">Depto.</th>
                                    <th class="text-right px-3 py-3 text-xs font-semibold text-institucional-500 uppercase tracking-wider">Peso bruto</th>
                                    <th class="text-right px-3 py-3 text-xs font-semibold text-institucional-500 uppercase tracking-wider">Valor</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gris-100">
                                <tr v-for="r in tabla.data" :key="r.operacion_id"
                                    class="hover:bg-gris-50 transition-all duration-200 ease-out">
                                    <td class="px-3 py-3 text-institucional-700">{{ r.gestion }}</td>
                                    <td class="px-3 py-3 text-institucional-700">{{ r.mes }}</td>
                                    <td class="px-3 py-3 text-institucional-700">{{ r.tipo_operacion }}</td>
                                    <td class="px-3 py-3 font-mono text-xs text-institucional-500">{{ r.codigo_nandina }}</td>
                                    <td class="px-3 py-3 truncate max-w-[200px] text-institucional-800">{{ r.producto }}</td>
                                    <td class="px-3 py-3 text-institucional-700">{{ r.pais }}</td>
                                    <td class="px-3 py-3 text-institucional-700">{{ r.departamento }}</td>
                                    <td class="px-3 py-3 text-right text-institucional-600">{{ fmt(r.peso_bruto_kg) }}</td>
                                    <td class="px-3 py-3 text-right font-semibold text-institucional-900">{{ fmtUsd(Number(r.valor_fob_usd || 0) + Number(r.valor_cif_frontera_usd || 0)) }}</td>
                                </tr>
                                <tr v-if="!tabla.data.length && !cargando">
                                    <td colspan="9" class="px-4 py-12 text-center text-institucional-400">Sin resultados para los filtros aplicados.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="flex items-center justify-between px-4 py-2.5 border-t border-gris-100 text-sm">
                        <span class="text-gris-500">Página {{ tabla.pagina }} de {{ tabla.ultima_pagina || 1 }} · {{ fmt(tabla.total) }} registros</span>
                        <div class="flex gap-2">
                            <button @click="pagina = Math.max(1, pagina - 1)" :disabled="pagina <= 1" class="px-3 py-1 rounded border border-gris-200 disabled:opacity-40 hover:bg-gris-50">Anterior</button>
                            <button @click="pagina = Math.min(tabla.ultima_pagina, pagina + 1)" :disabled="pagina >= tabla.ultima_pagina" class="px-3 py-1 rounded border border-gris-200 disabled:opacity-40 hover:bg-gris-50">Siguiente</button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </section>
</template>
