<script setup>
import { computed } from 'vue';

const props = defineProps({
    filas: { type: Array, default: () => [] },
    unidad: { type: String, default: 'USD' },
    mostrarAcumulado: { type: Boolean, default: true },
    mostrarVariacion: { type: Boolean, default: false },
});

function fmtValor(valor) {
    if (valor == null) return '—';
    return `${Number(valor).toLocaleString('es-BO', { maximumFractionDigits: 0 })} ${props.unidad}`;
}

function fmtPct(valor) {
    return valor == null ? '—' : `${Number(valor).toLocaleString('es-BO', { maximumFractionDigits: 2 })}%`;
}

function fmtVar(valor) {
    if (valor == null) return '—';
    return `${valor > 0 ? '+' : ''}${Number(valor).toLocaleString('es-BO', { maximumFractionDigits: 1 })}%`;
}

const hayFilas = computed(() => props.filas.length > 0);
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-sm">
            <thead>
                <tr class="border-b border-gris-100">
                    <th class="py-3 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-gris-500">#</th>
                    <th class="py-3 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-gris-500">Nombre</th>
                    <th class="py-3 pl-4 text-right text-xs font-semibold uppercase tracking-wider text-gris-500">Valor</th>
                    <th class="py-3 pl-4 text-right text-xs font-semibold uppercase tracking-wider text-gris-500">% total</th>
                    <th v-if="mostrarAcumulado" class="py-3 pl-4 text-right text-xs font-semibold uppercase tracking-wider text-gris-500">% acum.</th>
                    <th v-if="mostrarVariacion" class="py-3 pl-4 text-right text-xs font-semibold uppercase tracking-wider text-gris-500">Var.</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(fila, index) in filas" :key="fila.posicion ?? index" class="border-b border-gris-100">
                    <td class="py-3 pr-4 text-gris-500">{{ fila.posicion ?? index + 1 }}</td>
                    <td class="py-3 pr-4">
                        <div class="font-medium text-institucional-800">{{ fila.label ?? fila.descripcion ?? fila.item_codigo }}</div>
                    </td>
                    <td class="py-3 pl-4 text-right font-semibold text-institucional-800">{{ fmtValor(fila.valor) }}</td>
                    <td class="py-3 pl-4 text-right text-gris-500">{{ fmtPct(fila.porcentaje) }}</td>
                    <td v-if="mostrarAcumulado" class="py-3 pl-4 text-right text-gris-500">{{ fmtPct(fila.acumulado) }}</td>
                    <td
                        v-if="mostrarVariacion"
                        class="py-3 pl-4 text-right font-semibold"
                        :class="(fila.variacion ?? 0) >= 0 ? 'text-positivo' : 'text-rojo-600'"
                    >
                        {{ fmtVar(fila.variacion) }}
                    </td>
                </tr>
                <tr v-if="!hayFilas">
                    <td :colspan="mostrarVariacion ? 6 : 5" class="py-8 text-center text-gris-500">
                        Sin datos para estos filtros.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
