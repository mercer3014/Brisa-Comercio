<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { colorOrg, logoOrg } from '../../lib/orgColors';

const props = defineProps({
    org: { type: Object, required: true },
    modo: { type: String, default: 'grid' }, // 'grid' | 'lista'
});

const color = computed(() => props.org.color_primario || colorOrg(props.org.id));
const logo = computed(() => logoOrg(props.org.id) || logoOrg(props.org.sigla));
const registros = computed(() => Number(props.org.registros ?? 0).toLocaleString('es-BO'));

// Iconos de formato (estilo DATAX): chips de color con la sigla del formato.
const formatos = [
    { txt: 'CSV', bg: '#0E7490' },
    { txt: 'XLS', bg: '#15803D' },
    { txt: 'XLS', bg: '#16A34A' },
    { txt: 'PDF', bg: '#B91C1C' },
];
</script>

<template>
    <!-- ====================== VISTA LISTA ====================== -->
    <Link v-if="modo === 'lista'" :href="`/organizaciones/${org.id}`"
          class="tarjeta group flex flex-col sm:flex-row overflow-hidden hover:shadow-flotante transition-shadow">
        <!-- Banner con logo -->
        <div class="relative w-full sm:w-56 shrink-0 h-40 sm:h-auto flex items-center justify-center overflow-hidden"
             :style="{ background: `linear-gradient(135deg, ${color} 0%, ${color}cc 100%)` }">
            <img v-if="logo" :src="logo" :alt="org.sigla" class="max-h-24 max-w-[70%] object-contain drop-shadow" loading="lazy" />
            <span v-else class="text-3xl font-bold text-white">{{ org.sigla }}</span>
            <span class="absolute bottom-2 left-3 text-xs font-semibold uppercase tracking-wider text-white/90">{{ org.sigla }}</span>
        </div>
        <!-- Contenido -->
        <div class="flex flex-1 flex-col p-5">
            <h3 class="text-xl font-bold leading-tight text-institucional-900">{{ org.nombre }}</h3>
            <div class="mt-2 flex flex-wrap items-center gap-1.5">
                <span class="rounded-md bg-institucional-700 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-white">Dataset</span>
                <span v-for="(f, i) in formatos" :key="i" class="rounded px-1.5 py-0.5 text-[10px] font-bold text-white" :style="{ backgroundColor: f.bg }">{{ f.txt }}</span>
            </div>
            <p class="mt-3 flex-1 text-sm leading-relaxed text-institucional-500 line-clamp-2">{{ org.descripcion || 'Sin descripción disponible.' }}</p>
            <div class="mt-4 flex items-center justify-between">
                <span class="text-xs text-institucional-400">{{ registros }} registros · gestión {{ org.gestion_reciente || 's/d' }}</span>
                <span class="rounded-full border-2 px-5 py-1.5 text-sm font-semibold transition" :style="{ borderColor: color, color }">
                    Ver detalles
                </span>
            </div>
        </div>
    </Link>

    <!-- ====================== VISTA GRID ====================== -->
    <Link v-else :href="`/organizaciones/${org.id}`"
          class="tarjeta group flex h-full flex-col overflow-hidden hover:shadow-flotante transition-shadow">
        <!-- Banner con logo prominente -->
        <div class="relative h-44 flex items-center justify-center overflow-hidden"
             :style="{ background: `linear-gradient(135deg, ${color} 0%, ${color}cc 100%)` }">
            <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle at 20% 20%, #fff 1px, transparent 1px); background-size: 22px 22px;"></div>
            <div class="relative flex h-24 w-40 items-center justify-center rounded-xl bg-white/95 p-3 shadow-lg">
                <img v-if="logo" :src="logo" :alt="org.sigla" class="max-h-full max-w-full object-contain" loading="lazy" />
                <span v-else class="text-2xl font-bold" :style="{ color }">{{ org.sigla }}</span>
            </div>
            <span class="absolute bottom-3 left-4 text-sm font-bold uppercase tracking-wider text-white drop-shadow">{{ org.sigla }}</span>
        </div>

        <!-- Cuerpo -->
        <div class="flex flex-1 flex-col p-5">
            <h3 class="text-lg font-bold leading-snug text-institucional-900 line-clamp-2">{{ org.nombre }}</h3>
            <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
                <span class="rounded-md bg-institucional-700 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-white">Dataset</span>
                <span v-for="(f, i) in formatos" :key="i" class="rounded px-1.5 py-0.5 text-[10px] font-bold text-white" :style="{ backgroundColor: f.bg }">{{ f.txt }}</span>
            </div>
            <p class="mt-3 flex-1 text-sm leading-relaxed text-institucional-500 line-clamp-3">{{ org.descripcion || 'Sin descripción disponible.' }}</p>
            <div class="mt-4 flex items-center justify-between border-t border-gris-100 pt-4">
                <span class="text-xs text-institucional-400">{{ registros }} reg.</span>
                <span class="rounded-full border-2 px-4 py-1.5 text-xs font-semibold transition" :style="{ borderColor: color, color }">
                    Ver detalles →
                </span>
            </div>
        </div>
    </Link>
</template>
