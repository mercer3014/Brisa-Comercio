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
        <div class="mb-7">
            <h1 class="text-2xl font-bold text-institucional-900 tracking-tight">Configuración del sistema</h1>
            <p class="text-gris-500 text-sm mt-1">Parámetros que controlan el comportamiento de Ovxel.</p>
        </div>

        <div class="tarjeta overflow-hidden divide-y divide-gris-100">
            <div v-for="(p, i) in parametros" :key="p.clave" class="flex items-center gap-5 px-5 py-4">
                <div class="flex-1 min-w-0">
                    <div class="font-mono text-sm font-semibold text-rojo-700 truncate">{{ p.clave }}</div>
                    <div class="text-xs text-gris-500 mt-0.5">{{ p.descripcion }}</div>
                </div>
                <input v-model="form.parametros[i].valor" class="campo w-44" />
            </div>
            <div v-if="!parametros.length" class="px-5 py-10 text-center text-gris-400 text-sm">
                No hay parámetros de configuración.
            </div>
        </div>

        <div class="flex justify-end mt-5">
            <button @click="guardar" :disabled="form.processing" class="btn btn-primario">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
                {{ form.processing ? 'Guardando...' : 'Guardar cambios' }}
            </button>
        </div>
    </div>
</template>
