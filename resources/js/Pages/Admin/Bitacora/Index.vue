<script setup>
import { reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({ registros: Object, acciones: Array, filtros: Object });

const f = reactive({
    accion: props.filtros?.accion ?? '',
    entidad: props.filtros?.entidad ?? '',
    desde: props.filtros?.desde ?? '',
    hasta: props.filtros?.hasta ?? '',
});

function filtrar() {
    router.get('/admin/bitacora', { ...f }, { preserveState: true, replace: true });
}
function limpiar() {
    Object.keys(f).forEach((k) => (f[k] = ''));
    filtrar();
}
</script>

<template>
    <Head title="Bitacora" />
    <div class="max-w-6xl mx-auto">
        <h1 class="text-2xl font-bold text-slate-800 mb-1">Bitacora de auditoria</h1>
        <p class="text-slate-500 text-sm mb-5">Registro de acciones relevantes del sistema.</p>

        <div class="bg-white rounded-xl border border-slate-200 p-4 mb-4 grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Accion</label>
                <select v-model="f.accion" class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                    <option value="">Todas</option>
                    <option v-for="a in acciones" :key="a" :value="a">{{ a }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Entidad</label>
                <input v-model="f.entidad" class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Desde</label>
                <input v-model="f.desde" type="date" class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Hasta</label>
                <input v-model="f.hasta" type="date" class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm" />
            </div>
            <div class="flex gap-2">
                <button @click="filtrar" class="px-3 py-1.5 rounded bg-marca-700 text-white text-sm">Filtrar</button>
                <button @click="limpiar" class="px-3 py-1.5 rounded bg-slate-200 text-sm">Limpiar</button>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="text-left px-3 py-2 font-medium">Fecha</th>
                        <th class="text-left px-3 py-2 font-medium">Usuario</th>
                        <th class="text-left px-3 py-2 font-medium">Accion</th>
                        <th class="text-left px-3 py-2 font-medium">Entidad</th>
                        <th class="text-left px-3 py-2 font-medium">Registro</th>
                        <th class="text-left px-3 py-2 font-medium">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="r in registros.data" :key="r.bitacora_id" class="hover:bg-slate-50">
                        <td class="px-3 py-1.5 text-slate-500 text-xs whitespace-nowrap">{{ r.fecha_hora }}</td>
                        <td class="px-3 py-1.5">{{ r.usuario?.nombre_usuario ?? '—' }}</td>
                        <td class="px-3 py-1.5"><span class="text-xs bg-marca-100 text-marca-800 px-2 py-0.5 rounded font-mono">{{ r.accion }}</span></td>
                        <td class="px-3 py-1.5 text-slate-600">{{ r.entidad_afectada }}</td>
                        <td class="px-3 py-1.5 text-slate-500">{{ r.registro_afectado }}</td>
                        <td class="px-3 py-1.5 text-slate-400 text-xs">{{ r.ip_origen }}</td>
                    </tr>
                    <tr v-if="!registros.data.length">
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">Sin registros.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
