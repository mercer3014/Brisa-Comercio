<script setup>
/** Chips con los años realmente cargados por flujo (cobertura parcial). */
defineProps({
    cobertura: { type: Object, default: null },
    fuente: { type: String, default: 'INE' },
});
</script>

<template>
    <div v-if="cobertura" class="gd-cobertura">
        <span>Datos {{ fuente }} cargados:</span>
        <span class="gd-cob exp" :class="{ vacio: !cobertura.exp?.anios?.length }"><i></i>Exportaciones · {{ cobertura.exp?.etiqueta }}</span>
        <span class="gd-cob imp" :class="{ vacio: !cobertura.imp?.anios?.length }"><i></i>Importaciones · {{ cobertura.imp?.etiqueta }}</span>
    </div>
</template>
