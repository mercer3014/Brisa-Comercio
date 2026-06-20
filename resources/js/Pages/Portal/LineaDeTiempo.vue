<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import ChartCard from '../../Components/UI/ChartCard.vue';
import BaseApexChart from '../../Components/Charts/BaseApexChart.vue';
import { useChartData } from '../../Components/Composables/useChartData.js';
import { fmtCompacto, fmtUsd, ejeCompacto } from '../../lib/format';

const { data: timeline, cargando } = useChartData('/api/v1/timeline');
const anios = computed(() => timeline.value?.items ?? []);

const seleccion = ref(null);
function alternar(anio) {
    seleccion.value = seleccion.value === anio ? null : anio;
}
const datosSel = computed(() => anios.value.find((a) => a.anio === seleccion.value) ?? null);

// Gráfico de fondo: balanza por año (columna).
const serieFondo = computed(() => [{ name: 'Balanza', data: anios.value.map((a) => Math.round(a.balanza ?? 0)) }]);
const opcFondo = computed(() => ({
    chart: { type: 'bar' },
    plotOptions: { bar: { columnWidth: '45%', borderRadius: 3, distributed: true } },
    colors: anios.value.map((a) => (a.balanza >= 0 ? '#38A169' : '#C53030')),
    legend: { show: false },
    xaxis: { categories: anios.value.map((a) => a.anio) },
    yaxis: { labels: { formatter: ejeCompacto } },
    tooltip: { y: { formatter: (v) => fmtUsd(v) } },
}));
</script>

<template>
    <Head title="Línea de Tiempo" />

    <section class="bg-white border-b border-gris-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <p class="inline-flex items-center gap-2.5 text-[11px] font-bold uppercase tracking-[0.18em] text-rojo-600 mb-4"><span class="w-7 h-px bg-rojo-500"></span> Historia del comercio</p>
            <h1 class="titular-editorial text-4xl sm:text-5xl text-institucional-900">Línea de Tiempo</h1>
            <p class="text-institucional-500 mt-4 max-w-xl leading-relaxed text-lg">Variación anual del comercio exterior y los hitos que la marcaron.</p>
        </div>
    </section>

    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
        <!-- Variación anual de fondo -->
        <ChartCard titulo="Balanza comercial por año" fuente="INE — Bolivia" :cargando="cargando">
            <BaseApexChart type="bar" :series="serieFondo" :opciones="opcFondo" :height="300" :cargando="cargando" />
        </ChartCard>

        <!-- Timeline -->
        <div>
            <div v-if="cargando" class="space-y-4">
                <div v-for="n in 3" :key="n" class="tarjeta p-5 h-20 animate-pulse"></div>
            </div>
            <ol v-else class="relative border-l-2 border-gris-200 ml-3 space-y-6">
                <li v-for="a in anios" :key="a.anio" class="ml-6">
                    <span class="absolute -left-[9px] w-4 h-4 rounded-full ring-4 ring-white" :class="a.hito ? 'bg-rojo-600' : 'bg-institucional-400'"></span>
                    <button @click="alternar(a.anio)" class="tarjeta p-5 w-full text-left hover:border-rojo-200 transition">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-sm font-bold text-rojo-600">{{ a.anio }}</span>
                                <h3 v-if="a.hito" class="font-bold text-institucional-900 mt-0.5">{{ a.hito.titulo }}</h3>
                            </div>
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full" :class="a.balanza >= 0 ? 'bg-positivo-suave text-positivo' : 'bg-negativo-suave text-negativo'">
                                {{ a.balanza >= 0 ? 'Superávit' : 'Déficit' }} {{ fmtCompacto(a.balanza) }}
                            </span>
                        </div>
                        <p v-if="a.hito" class="text-sm text-gris-500 mt-1">{{ a.hito.descripcion }}</p>

                        <!-- Expansión con datos clave -->
                        <div v-if="seleccion === a.anio" class="grid grid-cols-3 gap-3 mt-4 pt-4 border-t border-gris-100">
                            <div><p class="text-[11px] text-gris-400 uppercase">Exportaciones</p><p class="font-bold text-institucional-900">{{ fmtCompacto(a.exportaciones) }}</p></div>
                            <div><p class="text-[11px] text-gris-400 uppercase">Importaciones</p><p class="font-bold text-institucional-900">{{ fmtCompacto(a.importaciones) }}</p></div>
                            <div><p class="text-[11px] text-gris-400 uppercase">Balanza</p><p class="font-bold" :class="a.balanza >= 0 ? 'text-positivo' : 'text-negativo'">{{ fmtCompacto(a.balanza) }}</p></div>
                        </div>
                        <p v-else class="text-[11px] text-gris-400 mt-2">Clic para ver datos clave →</p>
                    </button>
                </li>
                <li v-if="!anios.length" class="ml-6 text-gris-400 text-sm">Sin datos de evolución disponibles.</li>
            </ol>
        </div>
    </section>
</template>
