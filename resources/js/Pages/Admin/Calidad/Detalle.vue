<script setup>
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({ carga: Object, incidencias: Object, resumen: Object });

const colorSev = {
    INFO: 'bg-slate-100 text-slate-600',
    ADVERTENCIA: 'bg-amber-100 text-amber-700',
    ERROR: 'bg-red-100 text-red-700',
};
const colorTrat = {
    PENDIENTE: 'bg-slate-100 text-slate-600',
    CORREGIDO: 'bg-green-100 text-green-700',
    ACEPTADO: 'bg-blue-100 text-blue-700',
    DESCARTADO: 'bg-slate-200 text-slate-500',
};

function tratar(inc, estado) {
    router.patch(`/admin/calidad/incidencia/${inc.incidencia_id}`, { estado_tratamiento: estado }, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Calidad carga #${carga.carga_id}`" />
    <div class="max-w-6xl mx-auto">
        <Link href="/admin/calidad" class="text-sm text-marca-700 hover:underline">&larr; Volver al tablero</Link>
        <div class="flex items-center justify-between mt-2 mb-5">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Incidencias · carga #{{ carga.carga_id }}</h1>
                <p class="text-slate-500 text-sm">{{ carga.nombre_archivo }} · {{ carga.organizacion?.sigla }}</p>
            </div>
            <div class="flex gap-2">
                <span v-for="(n, sev) in resumen" :key="sev" class="text-xs px-2 py-1 rounded" :class="colorSev[sev]">{{ sev }}: {{ n }}</span>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="text-left px-3 py-2 font-medium">Fila</th>
                        <th class="text-left px-3 py-2 font-medium">Severidad</th>
                        <th class="text-left px-3 py-2 font-medium">Descripcion</th>
                        <th class="text-left px-3 py-2 font-medium">Valor</th>
                        <th class="text-left px-3 py-2 font-medium">Tratamiento</th>
                        <th class="text-right px-3 py-2 font-medium">Accion</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="i in incidencias.data" :key="i.incidencia_id" class="hover:bg-slate-50">
                        <td class="px-3 py-2 text-slate-500">{{ i.numero_fila }}</td>
                        <td class="px-3 py-2"><span class="text-xs px-2 py-0.5 rounded" :class="colorSev[i.severidad]">{{ i.severidad }}</span></td>
                        <td class="px-3 py-2 text-slate-700">{{ i.descripcion }}</td>
                        <td class="px-3 py-2 text-slate-500 font-mono text-xs">{{ i.valor_detectado }}</td>
                        <td class="px-3 py-2"><span class="text-xs px-2 py-0.5 rounded" :class="colorTrat[i.estado_tratamiento]">{{ i.estado_tratamiento }}</span></td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            <button @click="tratar(i, 'CORREGIDO')" class="text-green-700 hover:underline text-xs mr-2">Corregido</button>
                            <button @click="tratar(i, 'ACEPTADO')" class="text-blue-700 hover:underline text-xs mr-2">Aceptar</button>
                            <button @click="tratar(i, 'DESCARTADO')" class="text-slate-500 hover:underline text-xs">Descartar</button>
                        </td>
                    </tr>
                    <tr v-if="!incidencias.data.length">
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">Sin incidencias.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
