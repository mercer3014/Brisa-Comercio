<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import FacetaFiltro from '@/Components/FacetaFiltro.vue';

const props = defineProps({
    organizacionDefecto: Number,
    opciones: Object,
});

const orgId = ref(props.organizacionDefecto);
const busqueda = ref('');
const pagina = ref(1);
const porPagina = ref(25);
const cargando = ref(false);

// Filtros activos por faceta.
const filtros = reactive({
    tipo_operacion: [], gestion: [], mes: [], pais: [], zona: [], departamento: [],
    medio: [], via: [], seccion: [], capitulo: [], cuci: [], ciiu: [], gce: [], tnt: [], cuode: [],
});

const totales = ref({ total: 0, valor: 0, peso: 0 });
const tabla = ref({ data: [], total: 0, pagina: 1, ultima_pagina: 1 });
const facetas = ref({});

// Definicion de las facetas que se muestran en el panel.
const panel = [
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

let debounce = null;
async function consultar() {
    cargando.value = true;
    try {
        const { data } = await axios.post('/admin/explorador/consultar', {
            organizacion_id: orgId.value,
            pagina: pagina.value,
            por_pagina: porPagina.value,
            filtros: { ...filtros, busqueda: busqueda.value },
        });
        totales.value = data.totales;
        tabla.value = data.tabla;
        facetas.value = data.facetas;
    } finally {
        cargando.value = false;
    }
}

function consultarDesdeFiltro() {
    pagina.value = 1;
    consultar();
}

// Re-consultar al cambiar filtros (con debounce para la busqueda).
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

const fmt = (n) => new Intl.NumberFormat('es-BO', { maximumFractionDigits: 0 }).format(n || 0);
const fmtUsd = (n) => '$ ' + new Intl.NumberFormat('es-BO', { maximumFractionDigits: 0 }).format(n || 0);

onMounted(consultar);
</script>

<template>
    <Head title="Explorador" />

    <div class="flex gap-5 h-[calc(100vh-7rem)]">
        <!-- Panel de filtros -->
        <aside class="w-72 shrink-0 bg-white rounded-xl border border-slate-200 shadow-sm flex flex-col">
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
            <div class="flex-1 overflow-y-auto">
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
        <main class="flex-1 min-w-0 flex flex-col">
            <!-- Busqueda + KPIs -->
            <div class="mb-4">
                <input v-model="busqueda" placeholder="Buscar por descripcion de producto, pais o aduana..."
                       class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-marca-500" />
            </div>
            <div class="grid grid-cols-3 gap-4 mb-4">
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <div class="text-xs text-slate-500">Registros</div>
                    <div class="text-2xl font-bold text-slate-800">{{ fmt(totales.total) }}</div>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <div class="text-xs text-slate-500">Valor total (USD)</div>
                    <div class="text-2xl font-bold text-marca-700">{{ fmtUsd(totales.valor) }}</div>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <div class="text-xs text-slate-500">Peso bruto (kg)</div>
                    <div class="text-2xl font-bold text-slate-800">{{ fmt(totales.peso) }}</div>
                </div>
            </div>

            <!-- Tabla -->
            <div class="flex-1 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                <div class="overflow-auto flex-1">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600 sticky top-0">
                            <tr>
                                <th class="text-left px-3 py-2 font-medium">Gestion</th>
                                <th class="text-left px-3 py-2 font-medium">Mes</th>
                                <th class="text-left px-3 py-2 font-medium">Operacion</th>
                                <th class="text-left px-3 py-2 font-medium">NANDINA</th>
                                <th class="text-left px-3 py-2 font-medium">Producto</th>
                                <th class="text-left px-3 py-2 font-medium">Pais</th>
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
                                <td class="px-3 py-1.5 truncate max-w-[220px]">{{ r.producto }}</td>
                                <td class="px-3 py-1.5">{{ r.pais }}</td>
                                <td class="px-3 py-1.5 text-right">{{ fmt(r.peso_bruto_kg) }}</td>
                                <td class="px-3 py-1.5 text-right">{{ fmtUsd(Number(r.valor_fob_usd || 0) + Number(r.valor_cif_frontera_usd || 0)) }}</td>
                            </tr>
                            <tr v-if="!tabla.data.length && !cargando">
                                <td colspan="8" class="px-4 py-10 text-center text-slate-400">Sin resultados para los filtros aplicados.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Paginacion -->
                <div class="flex items-center justify-between px-4 py-2.5 border-t border-slate-100 text-sm">
                    <span class="text-slate-500">
                        Pagina {{ tabla.pagina }} de {{ tabla.ultima_pagina || 1 }} · {{ fmt(tabla.total) }} registros
                    </span>
                    <div class="flex gap-2">
                        <button @click="pagina = Math.max(1, pagina - 1)" :disabled="pagina <= 1" class="px-3 py-1 rounded border border-slate-200 disabled:opacity-40 hover:bg-slate-50">Anterior</button>
                        <button @click="pagina = Math.min(tabla.ultima_pagina, pagina + 1)" :disabled="pagina >= tabla.ultima_pagina" class="px-3 py-1 rounded border border-slate-200 disabled:opacity-40 hover:bg-slate-50">Siguiente</button>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
