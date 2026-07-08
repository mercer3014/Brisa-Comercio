<script setup>
import { ref, reactive, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    catalogo: Array,
    organizaciones: Array,
    gestiones: Array,
});

const params = reactive({
    tipo: props.catalogo[0]?.tipo ?? null,
    organizacion_id: props.organizaciones[0]?.organizacion_id ?? null,
    flujo: '',
    gestion_desde: props.gestiones?.[props.gestiones.length - 1] ?? null,
    gestion_hasta: props.gestiones?.[0] ?? null,
});

const reporte = ref(null);
const cargando = ref(false);

const reporteActual = computed(() => props.catalogo.find((c) => c.tipo === params.tipo));

async function generar() {
    cargando.value = true;
    reporte.value = null;
    try {
        const { data } = await axios.post('/admin/reportes/generar', { ...params });
        reporte.value = data;
    } finally {
        cargando.value = false;
    }
}

function exportar(formato) {
    const q = new URLSearchParams({ ...params, formato }).toString();
    window.location.href = `/admin/reportes/exportar?${q}`;
}

const fmt = (v, i) => (typeof v === 'number' && i > 0)
    ? new Intl.NumberFormat('es-BO', { maximumFractionDigits: 2 }).format(v)
    : v;
</script>

<template>
    <Head title="Reportes" />

    <div class="max-w-6xl mx-auto">
        <h1 class="text-2xl font-bold text-slate-800 mb-1">Reportes</h1>
        <p class="text-slate-500 text-sm mb-5">Reportes predefinidos con parámetros y exportación.</p>

        <!-- Parámetros -->
        <div class="bg-white rounded-xl border border-slate-200 p-4 mb-5">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-slate-600 mb-1">Reporte</label>
                    <select v-model="params.tipo" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        <option v-for="c in catalogo" :key="c.tipo" :value="c.tipo">{{ c.titulo }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Organización</label>
                    <select v-model="params.organizacion_id" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        <option v-for="o in organizaciones" :key="o.organizacion_id" :value="o.organizacion_id">{{ o.nombre }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Flujo</label>
                    <select v-model="params.flujo" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Ambos</option>
                        <option value="EXPORTACION">Exportación</option>
                        <option value="IMPORTACION">Importación</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Desde</label>
                        <select v-model="params.gestion_desde" class="w-full rounded border border-slate-300 px-2 py-2 text-sm">
                            <option v-for="g in gestiones" :key="g" :value="g">{{ g }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Hasta</label>
                        <select v-model="params.gestion_hasta" class="w-full rounded border border-slate-300 px-2 py-2 text-sm">
                            <option v-for="g in gestiones" :key="g" :value="g">{{ g }}</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="flex gap-2 mt-4">
                <button @click="generar" :disabled="cargando" class="bg-marca-700 hover:bg-marca-800 text-white px-5 py-2 rounded-lg text-sm font-medium disabled:opacity-60">
                    {{ cargando ? 'Generando...' : 'Generar reporte' }}
                </button>
                <div v-if="reporte" class="flex gap-2 ml-auto">
                    <button @click="exportar('xlsx')" class="px-3 py-2 rounded-lg text-sm bg-emerald-600 hover:bg-emerald-700 text-white">Excel</button>
                    <button @click="exportar('csv')" class="px-3 py-2 rounded-lg text-sm bg-slate-600 hover:bg-slate-700 text-white">CSV</button>
                    <button @click="exportar('pdf')" class="px-3 py-2 rounded-lg text-sm bg-red-600 hover:bg-red-700 text-white">PDF</button>
                </div>
            </div>
        </div>

        <!-- Resultado -->
        <div v-if="reporte" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
                <h2 class="font-semibold text-slate-700">{{ reporte.titulo }}</h2>
                <div class="flex gap-4 text-sm">
                    <span v-for="(v, k) in reporte.resumen" :key="k" class="text-slate-500">
                        {{ k }}: <strong class="text-slate-800">{{ typeof v === 'number' ? fmt(v, 1) : v }}</strong>
                    </span>
                </div>
            </div>
            <div class="overflow-auto max-h-[60vh]">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600 sticky top-0">
                        <tr>
                            <th v-for="(c, i) in reporte.columnas" :key="i" class="px-3 py-2 font-medium" :class="i > 0 ? 'text-right' : 'text-left'">{{ c }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(fila, fi) in reporte.filas" :key="fi" class="hover:bg-slate-50">
                            <td v-for="(celda, ci) in fila" :key="ci" class="px-3 py-1.5" :class="ci > 0 ? 'text-right' : 'text-left'">{{ fmt(celda, ci) }}</td>
                        </tr>
                        <tr v-if="!reporte.filas.length">
                            <td :colspan="reporte.columnas.length" class="px-4 py-8 text-center text-slate-400">Sin datos para los parámetros.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
