<script setup>
/**
 * Panel de filtros del portal: selects de organización, año, flujo, país y zona.
 * Emite v-model con el objeto de filtros. Cada select es opcional (se muestra
 * solo si se le pasan opciones o se habilita).
 */
import { reactive, watch } from 'vue';

const props = defineProps({
    modelValue: { type: Object, default: () => ({}) },
    organizaciones: { type: Array, default: () => [] }, // [{organizacion_id|id, sigla, nombre}]
    gestiones: { type: Array, default: () => [] },       // [2024, 2023, ...]
    paises: { type: Array, default: () => [] },          // [{id, nombre}]
    zonas: { type: Array, default: () => [] },           // [{id, nombre}]
    mostrarFlujo: { type: Boolean, default: true },
    mostrarOrg: { type: Boolean, default: true },
});
const emit = defineEmits(['update:modelValue', 'cambio']);

const f = reactive({
    organizacion_id: props.modelValue.organizacion_id ?? props.organizaciones?.[0]?.organizacion_id ?? props.organizaciones?.[0]?.id ?? 1,
    gestion: props.modelValue.gestion ?? props.gestiones?.[0] ?? null,
    flujo: props.modelValue.flujo ?? 'exp',
    pais_id: props.modelValue.pais_id ?? null,
    zona_id: props.modelValue.zona_id ?? null,
});

watch(f, () => {
    emit('update:modelValue', { ...f });
    emit('cambio', { ...f });
}, { deep: true });
</script>

<template>
    <div class="tarjeta p-4 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
        <label v-if="mostrarOrg && organizaciones.length" class="text-xs font-medium text-gris-500">
            Organización
            <select v-model="f.organizacion_id" class="campo mt-1 py-2 text-sm">
                <option v-for="o in organizaciones" :key="o.organizacion_id ?? o.id" :value="o.organizacion_id ?? o.id">
                    {{ o.sigla || o.nombre }}
                </option>
            </select>
        </label>

        <label v-if="gestiones.length" class="text-xs font-medium text-gris-500">
            Año
            <select v-model="f.gestion" class="campo mt-1 py-2 text-sm">
                <option v-for="g in gestiones" :key="g" :value="g">{{ g }}</option>
            </select>
        </label>

        <label v-if="mostrarFlujo" class="text-xs font-medium text-gris-500">
            Flujo
            <select v-model="f.flujo" class="campo mt-1 py-2 text-sm">
                <option value="exp">Exportación</option>
                <option value="imp">Importación</option>
                <option value="ambos">Ambos</option>
            </select>
        </label>

        <label v-if="paises.length" class="text-xs font-medium text-gris-500">
            País
            <select v-model="f.pais_id" class="campo mt-1 py-2 text-sm">
                <option :value="null">Todos</option>
                <option v-for="p in paises" :key="p.id" :value="p.id">{{ p.nombre }}</option>
            </select>
        </label>

        <label v-if="zonas.length" class="text-xs font-medium text-gris-500">
            Zona
            <select v-model="f.zona_id" class="campo mt-1 py-2 text-sm">
                <option :value="null">Todas</option>
                <option v-for="z in zonas" :key="z.id" :value="z.id">{{ z.nombre }}</option>
            </select>
        </label>

        <slot :filtros="f" />
    </div>
</template>
