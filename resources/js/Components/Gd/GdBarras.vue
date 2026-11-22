<script setup>
/** Lista de barras tipo "píldora" del mockup (Top productos). */
import { computed } from 'vue';
import { fmtM } from '@/lib/gd';

const props = defineProps({
    items: { type: Array, default: () => [] }, // [{nombre, valor, titulo?}]
    color: { type: String, default: '' },
    vacio: { type: String, default: 'Sin datos para esta gestión.' },
});
const emit = defineEmits(['elegir']);
const max = computed(() => Math.max(...props.items.map((i) => i.valor), 1));
</script>

<template>
    <ul class="pill-bars-list">
        <li v-for="(it, i) in items" :key="i" class="pill-bar-row" role="button" tabindex="0" style="cursor:pointer"
            :title="it.titulo || it.nombre" @click="emit('elegir', it)" @keydown.enter="emit('elegir', it)">
            <span class="nm">{{ it.nombre }}</span>
            <div class="pill-track"><div class="pill-fill" :style="{ width: Math.round((it.valor / max) * 100) + '%', ...(color ? { background: color } : {}) }"></div></div>
            <b class="vl">{{ fmtM(it.valor) }}</b>
        </li>
        <li v-if="!items.length" class="empty-state">{{ vacio }}</li>
    </ul>
</template>
