<script setup>
import { computed, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    pais: Object,
});

const page = usePage();
const permisos = computed(() => page.props.auth?.usuario?.permisos ?? []);
const puedeEditar = computed(() => permisos.value.includes('pais.editar'));

const flagUrl = (iso) => iso
    ? `https://flagcdn.com/w80/${iso}.png`
    : null;

const modalAbierto = ref(false);
const form = useForm({ powerbi_url: props.pais.powerbiUrl ?? '' });

function abrirModal() {
    form.powerbi_url = props.pais.powerbiUrl ?? '';
    form.clearErrors();
    modalAbierto.value = true;
}

function guardar() {
    form.put(`/admin/paises/${props.pais.id}`, {
        preserveScroll: true,
        onSuccess: () => (modalAbierto.value = false),
    });
}

function eliminar() {
    form.powerbi_url = '';
    form.put(`/admin/paises/${props.pais.id}`, {
        preserveScroll: true,
        onSuccess: () => (modalAbierto.value = false),
    });
}
</script>

<template>
    <Head :title="pais.nombre" />

    <div class="max-w-7xl mx-auto">
        <!-- Encabezado -->
        <div class="mb-6 flex items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <!-- Bandera -->
                <div class="shrink-0 w-16 h-11 rounded-lg overflow-hidden shadow-md border border-gris-200 bg-gris-100 flex items-center justify-center">
                    <img
                        v-if="pais.iso"
                        :src="flagUrl(pais.iso)"
                        :alt="pais.nombre"
                        class="w-full h-full object-cover"
                    />
                    <svg v-else class="w-7 h-7 text-gris-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18"/>
                    </svg>
                </div>

                <div>
                    <h1 class="text-2xl font-bold text-institucional-900 tracking-tight leading-none">{{ pais.nombre }}</h1>
                    <p class="text-gris-500 text-sm mt-1">Dashboard de comercio exterior · Power BI</p>
                </div>
            </div>

            <button
                v-if="puedeEditar"
                @click="abrirModal"
                class="shrink-0 inline-flex items-center gap-2 rounded-lg bg-rojo-600 px-4 py-2 text-sm font-medium text-white hover:bg-rojo-700 transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                </svg>
                {{ pais.powerbiUrl ? 'Editar URL' : 'Guardar URL' }}
            </button>
        </div>

        <!-- Power BI embed -->
        <div v-if="pais.powerbiUrl" class="tarjeta overflow-hidden" style="height: calc(100vh - 11rem);">
            <iframe
                :src="pais.powerbiUrl"
                frameborder="0"
                allowfullscreen
                class="w-full h-full"
            ></iframe>
        </div>

        <!-- Placeholder sin URL -->
        <div v-else class="tarjeta p-10 flex flex-col items-center justify-center text-center border-2 border-dashed border-gris-200" style="min-height: 400px;">
            <div class="w-20 h-14 rounded-xl overflow-hidden shadow-md border border-gris-200 bg-gris-100 flex items-center justify-center mb-5">
                <img
                    v-if="pais.iso"
                    :src="flagUrl(pais.iso)"
                    :alt="pais.nombre"
                    class="w-full h-full object-cover"
                />
                <svg v-else class="w-8 h-8 text-gris-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18"/>
                </svg>
            </div>
            <p class="text-institucional-500 font-semibold text-lg">Sin reporte asignado</p>
            <p class="text-gris-400 text-sm mt-2 max-w-sm">
                Aún no se ha configurado un dashboard de Power BI para
                <strong class="text-institucional-700">{{ pais.nombre }}</strong>.
            </p>
        </div>

        <!-- Modal: configurar URL de Power BI -->
        <div v-if="modalAbierto" class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6">
                <h3 class="text-lg font-semibold text-slate-800 mb-1">URL de Power BI · {{ pais.nombre }}</h3>
                <p class="text-sm text-slate-500 mb-4">Pega el enlace de publicación/incrustación del reporte de Power BI para este país.</p>

                <label class="block text-xs font-medium text-slate-600 mb-1">URL</label>
                <input
                    v-model="form.powerbi_url"
                    type="url"
                    placeholder="https://app.powerbi.com/view?r=..."
                    class="w-full rounded border border-slate-300 px-3 py-2 text-sm"
                />
                <p v-if="form.errors.powerbi_url" class="text-xs text-rojo-600 mt-1">{{ form.errors.powerbi_url }}</p>

                <div class="flex justify-between items-center gap-2 mt-6">
                    <button
                        v-if="pais.powerbiUrl"
                        @click="eliminar"
                        :disabled="form.processing"
                        class="px-4 py-2 rounded-lg text-sm text-rojo-600 hover:bg-rojo-50"
                    >
                        Eliminar URL
                    </button>
                    <div v-else></div>
                    <div class="flex gap-2">
                        <button @click="modalAbierto = false" class="px-4 py-2 rounded-lg text-sm bg-slate-100">Cancelar</button>
                        <button @click="guardar" :disabled="form.processing" class="px-4 py-2 rounded-lg text-sm bg-rojo-600 text-white hover:bg-rojo-700">Guardar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
