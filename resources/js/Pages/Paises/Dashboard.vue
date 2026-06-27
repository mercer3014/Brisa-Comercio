<script setup>
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    pais: Object,
});

const flagUrl = (iso) => iso
    ? `https://flagcdn.com/w80/${iso}.png`
    : null;
</script>

<template>
    <Head :title="pais.nombre" />

    <div class="max-w-7xl mx-auto">
        <!-- Encabezado -->
        <div class="mb-6 flex items-center gap-4">
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
    </div>
</template>
