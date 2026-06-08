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
    { key: 'tipo_operacion', titulo: 'Tipo de operacion' },
    { key: 'gestion', titulo: 'Gestion (anio)' },
    { key: 'mes', titulo: 'Mes' },
    { key: 'pais', titulo: 'Pais' },
    { key: 'zona', titulo: 'Zona' },
    { key: 'departamento', titulo: 'Departamento' },
    { key: 'medio', titulo: 'Medio de transporte' },
    { key: 'via', titulo: 'Via' },
    { key: 'seccion', titulo: 'Seccion NANDINA' },
    { key: 'capitulo', titulo: 'Capitulo NANDINA' },
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

// Graficos (barras horizontales)
function opcBarras(items, color) {
    return {
        chart: { type: 'bar', toolbar: { show: false } },
        plotOptions: { bar: { horizontal: true, borderRadius: 3, barHeight: '60%' } },
        colors: [color],
        dataLabels: { enabled: false },
        xaxis: { categories: items.map((i) => i.label), labels: { formatter: (v) => Number(v).toLocaleString('es-BO', { notation: 'compact' }) } },
        yaxis: { labels: { maxWidth: 150, style: { fontSize: '10px' } } },
        tooltip: { y: { formatter: (v) => fmtUsd(v) } },
        grid: { strokeDashArray: 3 },
    };
}
const seriePaises = computed(() => [{ name: 'Valor', data: graficos.value.top_paises.map((i) => Math.round(i.valor)) }]);
const serieProductos = computed(() => [{ name: 'Valor', data: graficos.value.top_productos.map((i) => Math.round(i.valor)) }]);
const opcPaises = computed(() => opcBarras(graficos.value.top_paises, '#2563eb'));
const opcProductos = computed(() => opcBarras(graficos.value.top_productos, '#7c3aed'));

onMounted(() => {
    leerUrl();
    consultar();
});
</script>

<template>
    <Head title="Explorar datos" />

    <section class="max-w-7xl mx-auto px-4 sm:px-6 py-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-slate-800">Explorador de datos</h1>
                <p class="text-slate-500 text-sm">Filtra a gusto y descarga el detalle. Fuente: INE - Bolivia.</p>
            </div>
            <button
                @click="filtrosAbiertos = !filtrosAbiertos"
                class="lg:hidden px-4 py-2 rounded-lg border border-slate-300 text-sm font-medium text-slate-700 bg-white"
            >
                {{ filtrosAbiertos ? 'Ocultar filtros' : 'Mostrar filtros' }}
            </button>
        </div>

        <div class="mt-5 flex flex-col lg:flex-row gap-5">
            <!-- Panel de filtros (colapsable en movil) -->
            <aside
                class="w-full lg:w-72 shrink-0 bg-white rounded-xl border border-slate-200 shadow-sm flex flex-col"
                :class="{ 'hidden lg:flex': !filtrosAbiertos }"
            >
                <div class="p-3 border-b border-slate-100 flex items-center justify-between">
                    <h2 class="font-semibold text-slate-700">Filtros</h2>
                    <button @click="limpiarTodo" class="text-xs text-marca-600 hover:underline">Limpiar todo</button>
                </div>
                <div class="p-3 border-b border-slate-100">
                    <label class="block text-xs font-medium text-slate-500 mb-1">Organizacion</label>
                    <select v-model="orgId" class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
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
                <!-- Busqueda -->
                <input v-model="busqueda" placeholder="Buscar por producto, pais o aduana..."
                       class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-marca-500 mb-4" />

                <!-- Resumen visual: KPIs -->
                <div class="grid grid-cols-3 gap-3 mb-4" :class="{ 'opacity-60': cargando }">
                    <div class="bg-white rounded-xl border border-slate-200 p-4">
                        <div class="text-xs text-slate-500">Operaciones</div>
                        <div class="text-xl sm:text-2xl font-bold text-slate-800">{{ fmt(totales.total) }}</div>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 p-4">
                        <div class="text-xs text-slate-500">Valor total</div>
                        <div class="text-xl sm:text-2xl font-bold text-marca-700">{{ fmtUsd(totales.valor) }}</div>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 p-4">
                        <div class="text-xs text-slate-500">Peso bruto (kg)</div>
                        <div class="text-xl sm:text-2xl font-bold text-slate-800">{{ fmt(totales.peso) }}</div>
                    </div>
                </div>

                <!-- Resumen visual: graficos del subconjunto -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="bg-white rounded-xl border border-slate-200 p-4">
                        <h3 class="text-sm font-semibold text-slate-700 mb-1">Top paises (del filtro)</h3>
                        <apexchart v-if="seriePaises[0].data.length" type="bar" height="240" :options="opcPaises" :series="seriePaises" />
                        <p v-else class="text-xs text-slate-400 py-8 text-center">Sin datos.</p>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 p-4">
                        <h3 class="text-sm font-semibold text-slate-700 mb-1">Top productos (del filtro)</h3>
                        <apexchart v-if="serieProductos[0].data.length" type="bar" height="240" :options="opcProductos" :series="serieProductos" />
                        <p v-else class="text-xs text-slate-400 py-8 text-center">Sin datos.</p>
                    </div>
                </div>

                <!-- Tabla detallada -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-2.5 border-b border-slate-100">
                        <span class="text-sm font-semibold text-slate-700">Detalle</span>
                        <div class="flex gap-2">
                            <button @click="exportar('xlsx')" class="text-xs px-2.5 py-1.5 rounded bg-green-50 text-green-700 hover:bg-green-100 font-medium">Excel</button>
                            <button @click="exportar('csv')" class="text-xs px-2.5 py-1.5 rounded bg-slate-100 text-slate-700 hover:bg-slate-200 font-medium">CSV</button>
                        </div>
                    </div>
                    <div class="overflow-auto max-h-[60vh]">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-slate-600 sticky top-0">
                                <tr>
                                    <th class="text-left px-3 py-2 font-medium">Gestion</th>
                                    <th class="text-left px-3 py-2 font-medium">Mes</th>
                                    <th class="text-left px-3 py-2 font-medium">Tipo</th>
                                    <th class="text-left px-3 py-2 font-medium">NANDINA</th>
                                    <th class="text-left px-3 py-2 font-medium">Producto</th>
                                    <th class="text-left px-3 py-2 font-medium">Pais</th>
                                    <th class="text-left px-3 py-2 font-medium">Depto.</th>
                                    <th class="text-right px-3 py-2 font-medium">Peso bruto</th>
                                    <th class="text-right px-3 py-2 font-medium">Valor</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="r in tabla.data" :key="r.operacion_id" class="hover:bg-slate-50">
                                    <td class="px-3 py-1.5">{{ r.gestion }}</td>
                                    <td class="px-3 py-1.5">{{ r.mes }}</td>
                                    <td class="px-3 py-1.5">{{ r.tipo_operacion }}</td>
                                    <td class="px-3 py-1.5 font-mono text-xs">{{ r.codigo_nandina }}</td>
                                    <td class="px-3 py-1.5 truncate max-w-[200px]">{{ r.producto }}</td>
                                    <td class="px-3 py-1.5">{{ r.pais }}</td>
                                    <td class="px-3 py-1.5">{{ r.departamento }}</td>
                                    <td class="px-3 py-1.5 text-right">{{ fmt(r.peso_bruto_kg) }}</td>
                                    <td class="px-3 py-1.5 text-right">{{ fmtUsd(Number(r.valor_fob_usd || 0) + Number(r.valor_cif_frontera_usd || 0)) }}</td>
                                </tr>
                                <tr v-if="!tabla.data.length && !cargando">
                                    <td colspan="9" class="px-4 py-10 text-center text-slate-400">Sin resultados para los filtros aplicados.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="flex items-center justify-between px-4 py-2.5 border-t border-slate-100 text-sm">
                        <span class="text-slate-500">Pagina {{ tabla.pagina }} de {{ tabla.ultima_pagina || 1 }} · {{ fmt(tabla.total) }} registros</span>
                        <div class="flex gap-2">
                            <button @click="pagina = Math.max(1, pagina - 1)" :disabled="pagina <= 1" class="px-3 py-1 rounded border border-slate-200 disabled:opacity-40 hover:bg-slate-50">Anterior</button>
                            <button @click="pagina = Math.min(tabla.ultima_pagina, pagina + 1)" :disabled="pagina >= tabla.ultima_pagina" class="px-3 py-1 rounded border border-slate-200 disabled:opacity-40 hover:bg-slate-50">Siguiente</button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </section>
</template>
