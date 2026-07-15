<script setup>
import { reactive, computed, ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import ChartCard from '../../Components/UI/ChartCard.vue';
import EstadoVacio from '../../Components/UI/EstadoVacio.vue';
import BaseApexChart from '../../Components/Charts/BaseApexChart.vue';
import { useChartData } from '../../Components/Composables/useChartData.js';
import { fmtUsd, ejeCompacto } from '../../lib/format';

const props = defineProps({
    organizaciones: { type: Array, default: () => [] },
    gestiones: { type: Array, default: () => [] },
    organizacionDefecto: { type: Number, default: 1 },
});

const estado = reactive({ gestion: props.gestiones?.[0] ?? null, organizacionId: props.organizacionDefecto });

// Vía a resaltar si se llega desde un panel del Inicio (?via=maritimo).
const viaSeleccionada = ref(null);

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const via = params.get('via');
    if (via) {
        viaSeleccionada.value = via;
        requestAnimationFrame(() => {
            document.getElementById(`via-${via}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    }
});

const { data, cargando, error } = useChartData('/api/v1/charts/comercio-por-via', () => ({
    gestion: estado.gestion,
    organizacion_id: estado.organizacionId,
}));

const items = computed(() => data.value?.items ?? []);
const categorias = computed(() => data.value?.categorias ?? []);
const series = computed(() => data.value?.series ?? []);
const meta = computed(() => data.value?.meta ?? {});
// Solo el INE publica desglose por vía de transporte (ver comentario en PortalApi::comercioPorVia).
const disponible = computed(() => meta.value?.disponible !== false);
const hayDatos = computed(() => disponible.value && categorias.value.length > 0 && series.value.length > 0);

const filas = computed(() => items.value.map((it) => ({
    label: it.label,
    expo: it.expo,
    impo: it.impo,
    balanza: it.balanza,
})));

const columnasTabla = [
    { key: 'label', label: 'Vía' },
    { key: 'expo', label: 'Exportaciones', alinear: 'right', formato: (v) => fmtUsd(v) },
    { key: 'impo', label: 'Importaciones', alinear: 'right', formato: (v) => fmtUsd(v) },
    { key: 'balanza', label: 'Balanza', alinear: 'right', formato: (v) => fmtUsd(v) },
];

const opcionesChart = computed(() => ({
    plotOptions: { bar: { borderRadius: 5, columnWidth: '55%' } },
    colors: ['#1A4B8C', '#C53030'],
    xaxis: { categories: categorias.value },
    yaxis: { labels: { formatter: ejeCompacto } },
    tooltip: { y: { formatter: (v) => fmtUsd(v) } },
    legend: { position: 'top' },
}));

// Icono + descripción por vía (mismo lenguaje visual que los paneles del Inicio).
const ICONOS = {
    maritimo: { icono: 'M3 13.5l1.5 5.25a1.5 1.5 0 001.44 1.08h11.12a1.5 1.5 0 001.44-1.08L21 13.5M4.5 13.5h15M5.25 13.5V8.25A1.5 1.5 0 016.75 6.75h10.5a1.5 1.5 0 011.5 1.5v5.25M12 4.5v2.25', color: '#1A4B8C', descripcion: 'Registrado como transporte intermodal camión/tren + barco hasta un puerto vecino (Bolivia no tiene costa propia).' },
    terrestre: { icono: 'M3 9.75A1.5 1.5 0 014.5 8.25h7.5v7.5H3v-5.25zM12 11.25h3.75l3 3v1.5H12v-4.5zM6.75 18.75a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM16.5 18.75a1.5 1.5 0 100-3 1.5 1.5 0 000 3z', color: '#2E7D32', descripcion: 'Carretera y ferrocarril hacia los países vecinos, sin tramo marítimo.' },
    aereo: { icono: 'M3.75 12l16.5-6-6 16.5-2.25-7.5-8.25-3z', color: '#C62828', descripcion: 'Carga y pasajeros que cruzan la frontera por vía aérea.' },
    otros: { icono: 'M12 6v12m6-6H6', color: '#64748b', descripcion: 'Fluvial, lacustre, postal, ductos y otros medios menores.' },
};
</script>

<template>
    <Head title="Comercio por vía de transporte" />

    <section class="bg-white border-b border-gris-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <p class="inline-flex items-center gap-2.5 text-[11px] font-bold uppercase tracking-[0.18em] text-rojo-600 mb-4"><span class="w-7 h-px bg-rojo-500"></span> Comercio exterior · Bolivia</p>
            <h1 class="titular-editorial text-4xl sm:text-5xl text-institucional-900">Comercio por vía de transporte</h1>
            <p class="text-institucional-500 mt-4 max-w-2xl leading-relaxed text-lg">Exportaciones e importaciones de Bolivia agrupadas por Marítimo, Terrestre, Aéreo y Otros medios. Elige la organización: por ahora solo el INE publica este desglose.</p>
        </div>
    </section>

    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-6">
        <div class="tarjeta p-4 flex flex-wrap items-end gap-3">
            <label class="text-xs font-medium text-gris-500">Organización
                <select v-model.number="estado.organizacionId" class="campo mt-1 py-2 text-sm w-72">
                    <option v-for="o in organizaciones" :key="o.organizacion_id" :value="o.organizacion_id">
                        {{ o.nombre }}{{ o.sigla ? ` (${o.sigla})` : '' }}
                    </option>
                </select>
            </label>
            <label class="text-xs font-medium text-gris-500">Gestión
                <select v-model.number="estado.gestion" class="campo mt-1 py-2 text-sm w-32">
                    <option v-for="g in gestiones" :key="g" :value="g">{{ g }}</option>
                </select>
            </label>
            <p v-if="meta.fuente" class="text-xs text-gris-400 ml-auto">Fuente: {{ meta.fuente }} · {{ meta.gestion }}</p>
        </div>

        <div v-if="error" class="tarjeta p-8 text-center text-negativo">
            No se pudo cargar la información: {{ error }}
        </div>

        <div v-else-if="!disponible && !cargando" class="tarjeta">
            <EstadoVacio
                titulo="Esta organización no publica desglose por vía de transporte"
                :mensaje="meta.nota || 'Solo el INE registra el medio de transporte de cada operación; las demás fuentes llegan agregadas por país, zona o producto.'"
            />
        </div>

        <template v-else>
            <!-- Tarjetas por vía -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div
                    v-for="it in items" :key="it.clave" :id="`via-${it.clave}`"
                    class="tarjeta p-5 transition-shadow"
                    :class="viaSeleccionada === it.clave ? 'ring-2 ring-rojo-500 shadow-flotante' : ''"
                >
                    <div class="flex items-center gap-3 mb-4">
                        <span class="shrink-0 inline-flex items-center justify-center w-10 h-10 rounded-xl" :style="{ backgroundColor: (ICONOS[it.clave]?.color ?? '#64748b') + '1a', color: ICONOS[it.clave]?.color ?? '#64748b' }">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" :d="ICONOS[it.clave]?.icono ?? ''"/></svg>
                        </span>
                        <h3 class="font-bold text-lg text-institucional-900">{{ it.label }}</h3>
                    </div>
                    <dl class="space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-institucional-400">Exportado</dt>
                            <dd class="font-semibold text-institucional-900">{{ fmtUsd(it.expo) }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-institucional-400">Importado</dt>
                            <dd class="font-semibold text-institucional-900">{{ fmtUsd(it.impo) }}</dd>
                        </div>
                        <div class="flex items-center justify-between pt-2 border-t border-gris-100">
                            <dt class="text-institucional-400">Balanza</dt>
                            <dd class="font-semibold" :class="it.balanza >= 0 ? 'text-positivo' : 'text-rojo-600'">{{ fmtUsd(it.balanza) }}</dd>
                        </div>
                    </dl>
                    <p v-if="it.total === 0" class="text-xs text-amber-600 mt-4 leading-relaxed">
                        El INE no clasificó envíos como {{ it.label.toLowerCase() }} en {{ meta.gestion }}. Prueba otra gestión (ej. 2022).
                    </p>
                    <p v-else class="text-xs text-institucional-400 mt-4 leading-relaxed">{{ ICONOS[it.clave]?.descripcion }}</p>
                </div>
            </div>

            <ChartCard
                titulo="Exportaciones vs. importaciones por vía"
                subtitulo="Valor en USD por vía de transporte, para la gestión seleccionada"
                :fuente="`${meta.fuente ?? 'INE'} — Bolivia`"
                :cargando="cargando"
                :columnas="columnasTabla"
                :filas="filas"
            >
                <template v-if="hayDatos">
                    <BaseApexChart :key="meta.ultima_actualizacion ?? estado.gestion" type="bar" :series="series" :opciones="opcionesChart" :height="360" :cargando="cargando" />
                </template>
                <EstadoVacio
                    v-else-if="!cargando"
                    titulo="Sin datos para esta gestión"
                    mensaje="Selecciona otra gestión para ver el comercio por vía de transporte."
                />
            </ChartCard>
        </template>
    </section>
</template>
