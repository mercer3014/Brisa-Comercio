<script setup>
/** Pinta un texto con marcas [[clave|texto]] como términos del glosario. */
import { computed } from 'vue';
import GdGlosario from '@/Components/Gd/GdGlosario.vue';
import { segmentos } from '@/lib/educacion';

const props = defineProps({
    texto: { type: String, default: '' },
    tag: { type: String, default: 'p' },
});
const partes = computed(() => segmentos(props.texto));
</script>

<template>
    <component :is="tag">
        <template v-for="(s, i) in partes" :key="i">
            <GdGlosario v-if="s.clave" :clave="s.clave" :texto="s.t" />
            <template v-else>{{ s.t }}</template>
        </template>
    </component>
</template>
