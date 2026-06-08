<script setup>
import { useForm, Head } from '@inertiajs/vue3';

const props = defineProps({ parametros: Array });

const form = useForm({
    parametros: props.parametros.map((p) => ({ clave: p.clave, valor: p.valor })),
});

function guardar() {
    form.put('/admin/configuracion', { preserveScroll: true });
}
</script>

<template>
    <Head title="Configuracion" />
    <div class="max-w-3xl mx-auto">
        <h1 class="text-2xl font-bold text-slate-800 mb-1">Configuracion del sistema</h1>
        <p class="text-slate-500 text-sm mb-5">Parametros que controlan el comportamiento de ComexHub.</p>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm divide-y divide-slate-100">
            <div v-for="(p, i) in parametros" :key="p.clave" class="p-4 flex items-center gap-4">
                <div class="flex-1">
                    <div class="font-mono text-sm text-marca-800">{{ p.clave }}</div>
                    <div class="text-xs text-slate-500">{{ p.descripcion }}</div>
                </div>
                <input v-model="form.parametros[i].valor" class="w-40 rounded border border-slate-300 px-3 py-1.5 text-sm" />
            </div>
        </div>

        <div class="flex justify-end mt-4">
            <button @click="guardar" :disabled="form.processing" class="px-5 py-2 rounded-lg bg-marca-700 hover:bg-marca-800 text-white text-sm disabled:opacity-60">
                Guardar cambios
            </button>
        </div>
    </div>
</template>
