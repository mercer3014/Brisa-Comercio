<script setup>
import { ref, watch, onBeforeUnmount } from 'vue';

const props = defineProps({
    mensaje: { type: String, default: '' },
    tipo: { type: String, default: 'exito' }, // 'exito' | 'error'
    duracion: { type: Number, default: 5000 },
});

const visible = ref(false);
let temporizador = null;

function programarCierre() {
    clearTimeout(temporizador);
    temporizador = setTimeout(() => { visible.value = false; }, props.duracion);
}

watch(
    () => props.mensaje,
    (nuevo) => {
        if (nuevo) {
            visible.value = true;
            programarCierre();
        }
    },
    { immediate: true }
);

onBeforeUnmount(() => clearTimeout(temporizador));
</script>

<template>
    <Teleport to="body">
        <Transition name="toast">
            <div
                v-if="visible && mensaje"
                class="fixed top-20 right-4 sm:right-6 z-[100] w-[calc(100%-2rem)] max-w-sm"
                role="status"
                aria-live="polite"
            >
                <div
                    class="flex items-start gap-3 rounded-xl border bg-white shadow-lg px-4 py-3.5"
                    :class="tipo === 'error' ? 'border-negativo/30' : 'border-positivo/30'"
                >
                    <span
                        class="mt-0.5 flex items-center justify-center w-6 h-6 rounded-full shrink-0"
                        :class="tipo === 'error' ? 'bg-negativo-suave text-negativo' : 'bg-positivo-suave text-positivo'"
                    >
                        <svg v-if="tipo !== 'error'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v3.75m0 3.75h.008M10.29 3.86L1.82 18a1.5 1.5 0 001.29 2.25h17.78a1.5 1.5 0 001.29-2.25L13.71 3.86a1.5 1.5 0 00-2.42 0z" />
                        </svg>
                    </span>

                    <p class="text-sm font-medium leading-snug pt-0.5" :class="tipo === 'error' ? 'text-negativo' : 'text-positivo'">
                        {{ mensaje }}
                    </p>

                    <button
                        class="ml-auto -mr-1 -mt-1 p-1 rounded-md text-gris-400 hover:text-gris-600 hover:bg-gris-100 transition shrink-0"
                        @click="visible = false"
                        aria-label="Cerrar notificacion"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.toast-enter-active {
    transition: opacity 0.25s ease, transform 0.25s ease;
}
.toast-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateX(16px);
}
</style>
