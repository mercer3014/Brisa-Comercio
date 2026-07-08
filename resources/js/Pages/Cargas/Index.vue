<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({ cargas: Object });

const colorEstado = {
    PENDIENTE: 'bg-slate-200 text-slate-600',
    PROCESANDO: 'bg-blue-100 text-blue-700',
    COMPLETADO: 'bg-green-100 text-green-700',
    FALLIDO: 'bg-red-100 text-red-700',
};
</script>

<template>
    <Head title="Cargas" />

    <div class="max-w-6xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Cargas de archivos</h1>
                <p class="text-slate-500 text-sm">Historial de archivos subidos y su procesamiento.</p>
            </div>
            <Link href="/admin/cargas/nueva" class="bg-marca-700 hover:bg-marca-800 text-white px-4 py-2 rounded-lg text-sm font-medium">
                + Nueva carga
            </Link>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium">#</th>
                        <th class="text-left px-4 py-3 font-medium">Archivo</th>
                        <th class="text-left px-4 py-3 font-medium">Organización</th>
                        <th class="text-left px-4 py-3 font-medium">Flujo</th>
                        <th class="text-center px-4 py-3 font-medium">Leidas</th>
                        <th class="text-center px-4 py-3 font-medium">Validas</th>
                        <th class="text-center px-4 py-3 font-medium">Error</th>
                        <th class="text-center px-4 py-3 font-medium">Estado</th>
                        <th class="text-left px-4 py-3 font-medium">Fecha</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="c in cargas.data" :key="c.carga_id" class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-slate-400">{{ c.carga_id }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800">{{ c.nombre_archivo }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ c.organizacion?.sigla }}</td>
                        <td class="px-4 py-3">
                            <span class="text-xs px-2 py-0.5 rounded" :class="c.tipo_flujo === 'EXPORTACION' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                                {{ c.tipo_flujo }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center text-slate-600">{{ c.total_filas_leidas ?? 0 }}</td>
                        <td class="px-4 py-3 text-center text-green-600">{{ c.total_filas_validas ?? 0 }}</td>
                        <td class="px-4 py-3 text-center text-red-600">{{ c.total_filas_error ?? 0 }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="text-xs px-2 py-0.5 rounded" :class="colorEstado[c.estado]">{{ c.estado }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-500 text-xs">{{ c.fecha_carga }}</td>
                    </tr>
                    <tr v-if="!cargas.data.length">
                        <td colspan="9" class="px-4 py-8 text-center text-slate-400">No hay cargas registradas.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
