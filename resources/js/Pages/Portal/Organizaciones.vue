<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import OrgCard from '../../Components/UI/OrgCard.vue';
import { useChartData } from '../../Components/Composables/useChartData.js';

defineProps({
    organizaciones: { type: Array, default: () => [] },
    gestiones: { type: Array, default: () => [] },
    organizacionDefecto: { type: Number, default: 1 },
});

const { data, cargando, error } = useChartData('/api/v1/organizaciones');
const orgs = computed(() => data.value?.data ?? []);

const vista = ref('grid'); // 'grid' | 'lista'
</script>

<template>
    <Head title="Organizaciones" />

    <section class="bg-white border-b border-gris-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <p class="inline-flex items-center gap-2.5 text-[11px] font-bold uppercase tracking-[0.18em] text-rojo-600 mb-4">
                <span class="w-7 h-px bg-rojo-500"></span> Fuentes de datos
            </p>
            <h1 class="titular-editorial text-4xl sm:text-5xl text-institucional-900">Organizaciones</h1>
            <p class="text-institucional-500 mt-4 max-w-xl leading-relaxed text-lg">
                Cuatro fuentes oficiales de comercio exterior y datos agrícolas. Elige una para ver sus gráficos y descargar sus datos.
            </p>
        </div>
    </section>

    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <!-- Barra de vista (grid / lista) -->
        <div class="flex items-center justify-between mb-6">
            <span class="text-sm text-institucional-500"><strong class="text-institucional-900">{{ orgs.length }}</strong> fuentes disponibles</span>
            <div class="flex items-center gap-1 rounded-lg border border-gris-200 p-1">
                <button @click="vista = 'grid'" :class="vista === 'grid' ? 'bg-institucional-900 text-white' : 'text-gris-400 hover:text-institucional-700'" class="rounded-md p-1.5 transition" title="Vista cuadrícula">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                </button>
                <button @click="vista = 'lista'" :class="vista === 'lista' ? 'bg-institucional-900 text-white' : 'text-gris-400 hover:text-institucional-700'" class="rounded-md p-1.5 transition" title="Vista lista">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                </button>
            </div>
        </div>

        <!-- Skeleton -->
        <div v-if="cargando" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="n in 6" :key="n" class="tarjeta overflow-hidden animate-pulse">
                <div class="h-44 bg-gris-100"></div>
                <div class="p-5 space-y-3"><div class="h-4 bg-gris-100 rounded w-3/4"></div><div class="h-2 bg-gris-100 rounded"></div><div class="h-2 bg-gris-100 rounded w-5/6"></div></div>
            </div>
        </div>

        <div v-else-if="error" class="tarjeta p-8 text-center text-negativo">
            No se pudieron cargar las organizaciones: {{ error }}
        </div>

        <!-- Grid -->
        <div v-else-if="vista === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <OrgCard v-for="o in orgs" :key="o.id" :org="o" modo="grid" />
        </div>

        <!-- Lista -->
        <div v-else class="space-y-5">
            <OrgCard v-for="o in orgs" :key="o.id" :org="o" modo="lista" />
        </div>
    </section>
</template>
