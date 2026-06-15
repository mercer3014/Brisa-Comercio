<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    titulo: String,
    opciones: { type: Array, default: () => [] }, // [{id, label}]
    conteos: { type: Object, default: () => ({}) }, // {id: n}
    modelValue: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:modelValue']);

const abierto = ref(false);
const busqueda = ref('');

const filtradas = computed(() => {
    const q = busqueda.value.trim().toLowerCase();
    return props.opciones
        .filter((o) => !q || String(o.label).toLowerCase().includes(q))
        .map((o) => ({ ...o, n: props.conteos[o.id] ?? 0 }))
        .sort((a, b) => b.n - a.n);
});

const seleccionadas = computed(() => props.modelValue.length);

function toggle(id) {
    const set = new Set(props.modelValue);
    set.has(id) ? set.delete(id) : set.add(id);
    emit('update:modelValue', [...set]);
}

function limpiar() {
    emit('update:modelValue', []);
}
</script>

<template>
    <div class="border-b border-gris-100">
        <button @click="abierto = !abierto" class="w-full flex items-center justify-between px-3 py-2.5 text-sm font-semibold text-institucional-900 hover:bg-gris-50">
            <span>
                {{ titulo }}
                <span v-if="seleccionadas" class="ml-1 inline-flex items-center justify-center text-xs bg-rojo-600 text-white rounded-full px-1.5">{{ seleccionadas }}</span>
            </span>
            <svg class="w-4 h-4 text-gris-400 transition-transform" :class="{ 'rotate-180': abierto }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div v-show="abierto" class="px-3 pb-3">
            <input v-model="busqueda" placeholder="Buscar..." class="w-full mb-2 rounded border border-gris-200 px-2 py-1 text-xs focus:ring-1 focus:ring-institucional-400" />
            <div v-if="seleccionadas" class="text-right mb-1">
                <button @click="limpiar" class="text-xs text-rojo-600 hover:underline">Limpiar</button>
            </div>
            <div class="max-h-52 overflow-y-auto space-y-0.5">
                <label v-for="o in filtradas" :key="o.id"
                       class="flex items-center justify-between gap-2 text-sm px-1 py-0.5 rounded cursor-pointer"
                       :class="modelValue.includes(o.id) ? 'bg-rojo-50' : 'hover:bg-gris-50'"
                       :style="o.n === 0 && !modelValue.includes(o.id) ? 'opacity:0.4' : ''">
                    <span class="flex items-center gap-2 min-w-0">
                        <input type="checkbox" :checked="modelValue.includes(o.id)" @change="toggle(o.id)" class="rounded text-rojo-600 focus:ring-rojo-500 shrink-0" />
                        <span class="truncate" :class="modelValue.includes(o.id) ? 'text-institucional-900 font-medium' : 'text-gris-700'">{{ o.label }}</span>
                    </span>
                    <span class="text-xs text-gris-400 shrink-0">{{ o.n }}</span>
                </label>
                <div v-if="!filtradas.length" class="text-xs text-gris-400 py-1">Sin opciones.</div>
            </div>
        </div>
    </div>
</template>
