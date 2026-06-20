<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { CountUp } from 'countup.js';

const props = defineProps({
    titulo: { type: String, required: true },
    valor: { type: Number, default: 0 },
    sufijo: { type: String, default: '' },
    prefijo: { type: String, default: '' },
    decimales: { type: Number, default: 0 },
    variacion: { type: Number, default: null },
    subtitulo: { type: String, default: '' },
    sparkline: { type: Array, default: () => [] },
    color: { type: String, default: '#193153' },
    icono: { type: String, default: '' },
});

const numero = ref(null);
let countup = null;

function animar(destino) {
    if (!numero.value) return;
    if (!countup) {
        countup = new CountUp(numero.value, destino, {
            duration: 1.15,
            separator: '.',
            decimal: ',',
            decimalPlaces: props.decimales,
            suffix: props.sufijo,
        });
        countup.start();
    } else {
        countup.update(destino);
    }
}

onMounted(() => nextTick(() => animar(props.valor)));
watch(() => props.valor, animar);

const positiva = computed(() => (props.variacion ?? 0) >= 0);

const sparkPath = computed(() => {
    const data = props.sparkline;
    if (!data || data.length < 2) return '';
    const min = Math.min(...data);
    const max = Math.max(...data);
    const spread = max - min || 1;
    const width = 96;
    const height = 26;
    return data.map((value, index) => {
        const x = (index / (data.length - 1)) * width;
        const y = height - ((value - min) / spread) * height;
        return `${index === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`;
    }).join(' ');
});
</script>

<template>
    <article class="tarjeta flex h-full flex-col justify-between px-5 py-5">
        <div class="flex items-start justify-between gap-4 border-b border-gris-100 pb-4">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-institucional-400">{{ titulo }}</div>
                <p v-if="subtitulo" class="mt-2 text-sm text-institucional-500">{{ subtitulo }}</p>
            </div>
            <span v-if="icono" class="kpi-icon shrink-0" :style="{ color }">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="icono" />
                </svg>
            </span>
        </div>

        <div class="pt-5">
            <div class="flex items-baseline gap-1 overflow-hidden">
                <span v-if="prefijo" class="shrink-0 text-xl font-bold leading-none text-institucional-400">{{ prefijo.trim() }}</span>
                <div ref="numero" class="text-[1.55rem] font-bold leading-none text-institucional-900 whitespace-nowrap tabular-nums sm:text-[1.85rem]">0</div>
            </div>
            <div class="mt-4 flex items-end justify-between gap-4">
                <div v-if="variacion !== null" class="text-sm font-semibold" :class="positiva ? 'text-positivo' : 'text-rojo-600'">
                    {{ positiva ? '▲' : '▼' }} {{ positiva ? '+' : '' }}{{ Number(variacion).toLocaleString('es-BO', { maximumFractionDigits: 1 }) }}%
                </div>
                <svg v-if="sparkPath" class="h-7 w-24 shrink-0" viewBox="0 0 96 26" preserveAspectRatio="none">
                    <path :d="sparkPath" fill="none" :stroke="color" stroke-width="1.6" stroke-linecap="square" />
                </svg>
            </div>
        </div>
    </article>
</template>
