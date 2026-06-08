<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({ cargas: Object });
</script>

<template>
    <Head title="Calidad de datos" />
    <div class="max-w-5xl mx-auto">
        <h1 class="text-2xl font-bold text-slate-800 mb-1">Tablero de calidad</h1>
        <p class="text-slate-500 text-sm mb-5">Incidencias detectadas durante el procesamiento de cargas.</p>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium">Carga</th>
                        <th class="text-left px-4 py-3 font-medium">Archivo</th>
                        <th class="text-center px-4 py-3 font-medium">Total</th>
                        <th class="text-center px-4 py-3 font-medium">Errores</th>
                        <th class="text-center px-4 py-3 font-medium">Pendientes</th>
                        <th class="text-right px-4 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="c in cargas.data" :key="c.carga_id" class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-slate-400">#{{ c.carga_id }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800">{{ c.nombre_archivo }}</td>
                        <td class="px-4 py-3 text-center">{{ c.incidencias_count }}</td>
                        <td class="px-4 py-3 text-center text-red-600">{{ c.incidencias_error_count }}</td>
                        <td class="px-4 py-3 text-center text-amber-600">{{ c.incidencias_pendientes_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="`/admin/calidad/${c.carga_id}`" class="text-marca-700 hover:underline">Ver incidencias</Link>
                        </td>
                    </tr>
                    <tr v-if="!cargas.data.length">
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">No hay cargas con incidencias.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
