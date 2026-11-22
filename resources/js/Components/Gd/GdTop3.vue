<script setup>
/** Top 3 de países (destinos u orígenes) con barra relativa, como el mockup. */
import { computed } from 'vue';
import { fmtM, fmtPct, bandera } from '@/lib/gd';

const props = defineProps({
    titulo: { type: String, default: '' },
    items: { type: Array, default: () => [] }, // [{iso2, nombre, valor, pct}]
    vacio: { type: String, default: 'Sin datos para esta gestión.' },
});
const max = computed(() => Math.max(...props.items.map((i) => i.valor), 1));
</script>

<template>
    <div class="top3-dest">
        <small>{{ titulo }}</small>
        <ol>
            <li v-for="(x, i) in items" :key="x.clave || i">
                <span class="t3-pos">{{ i + 1 }}</span>
                <span class="t3-code" :title="x.iso2 ? bandera(x.iso2) : ''">{{ x.iso2 || '—' }}</span>
                <span class="t3-name"><b>{{ x.nombre }}</b><i :style="{ width: Math.round((x.valor / max) * 100) + '%' }"></i></span>
                <span class="t3-val"><b>{{ fmtM(x.valor) }}</b><small>{{ fmtPct(x.pct) }}</small></span>
            </li>
            <li v-if="!items.length" class="empty-state">{{ vacio }}</li>
        </ol>
    </div>
</template>
