<script setup>
/** Estado "sin datos": nunca se muestran ceros ni cifras de ejemplo. */
defineProps({
    titulo: { type: String, default: 'Sin datos' },
    mensaje: { type: String, default: '' },
});
</script>

<template>
    <div class="gd-sindatos">
        <span class="gd-tag-sindatos">Sin datos</span>
        <b>{{ titulo }}</b>
        <small v-if="mensaje">{{ mensaje }}</small>
        <slot />
    </div>
</template>
