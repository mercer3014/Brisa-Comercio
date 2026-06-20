<script setup>
/** Estado vacío elegante para cuando una API no tiene datos todavía. */
defineProps({
    titulo: { type: String, default: 'Datos en proceso de carga' },
    mensaje: { type: String, default: 'Esta fuente aún no tiene datos disponibles. Vuelve pronto.' },
    icono: { type: String, default: 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z' },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center text-center py-12 px-6">
        <div class="w-14 h-14 rounded-2xl bg-gris-100 flex items-center justify-center mb-4">
            <svg class="w-7 h-7 text-gris-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" :d="icono" />
            </svg>
        </div>
        <h3 class="font-bold text-institucional-900">{{ titulo }}</h3>
        <p class="text-sm text-gris-500 mt-1.5 max-w-sm">{{ mensaje }}</p>
        <slot />
    </div>
</template>
