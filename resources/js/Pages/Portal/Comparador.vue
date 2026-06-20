<script setup>
import { reactive, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import ChartCard from '../../Components/UI/ChartCard.vue';
import BaseApexChart from '../../Components/Charts/BaseApexChart.vue';
import EstadoVacio from '../../Components/UI/EstadoVacio.vue';
import { useChartData } from '../../Components/Composables/useChartData.js';
import { fmtCompacto, fmtPct, fmtUsd, ejeCompacto } from '../../lib/format';

const props = defineProps({
    gestiones: { type: Array, default: () => [] },
});

const estado = reactive({
    modo: 'anio',
    dimension: 'top-productos',
    flujo: 'exp',
    anioA: props.gestiones?.[1] ?? props.gestiones?.[0] ?? null,
    anioB: props.gestiones?.[0] ?? null,
    paisA: null,
    paisB: null,
    productoA: null,
    productoB: null,
});

const { data: paisesResp } = useChartData('/api/v1/filtros/paises', () => ({ limit: 80 }));
const { data: productosResp } = useChartData('/api/v1/filtros/productos', () => ({ limit: 80 }));
const paises = computed(() => paisesResp.value?.data ?? []);
const productos = computed(() => productosResp.value?.data ?? []);

const params = () => ({
    modo: estado.modo,
    dimension: estado.dimension,
    flujo: estado.flujo,
    anio_a: estado.anioA,
    anio_b: estado.anioB,
    pais_a: estado.paisA,
    pais_b: estado.paisB,
    producto_a: estado.productoA,
    producto_b: estado.productoB,
    limit: 12,
});

const { data, cargando, error } = useChartData('/api/v1/comparador', params);

const categorias = computed(() => data.value?.categorias ?? []);
const series = computed(() => data.value?.series ?? []);
const filas = computed(() => data.value?.filas ?? []);
const hayDatos = computed(() => categorias.value.length > 0 && series.value.length > 0);

const tituloTarjeta = computed(() => {
    if (estado.modo === 'pais') return 'País A vs País B';
    if (estado.modo === 'producto') return 'Producto A vs Producto B';
    return `Año ${estado.anioA} vs ${estado.anioB}`;
});

const columnasTabla = computed(() => {
    if (estado.modo === 'anio') {
        return [
            { key: 'label', label: 'Nombre' },
            { key: 'valor_a', label: String(estado.anioA ?? 'A'), alinear: 'right', formato: (v) => fmtCompacto(v) },
            { key: 'valor_b', label: String(estado.anioB ?? 'B'), alinear: 'right', formato: (v) => fmtCompacto(v) },
            { key: 'variacion', label: 'Variación', alinear: 'right', formato: (v) => fmtPct(v, true) },
        ];
    }

    const nombresSeries = series.value.map((s) => s.name);
    return [
        { key: 'gestion', label: 'Año' },
        ...nombresSeries.map((nombre) => ({ key: nombre, label: nombre, alinear: 'right', formato: (v) => fmtCompacto(v) })),
    ];
});

const opcionesChart = computed(() => {
    if (estado.modo === 'anio') {
        return {
            plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '56%' } },
            colors: ['#94a3b8', '#1A4B8C'],
            xaxis: { categories: categorias.value, labels: { formatter: ejeCompacto } },
            yaxis: { labels: { style: { fontSize: '10px' }, maxWidth: 220 } },
            tooltip: { y: { formatter: (v) => fmtUsd(v) } },
            legend: { position: 'top' },
        };
    }

    return {
        stroke: { width: 3, curve: 'smooth' },
        colors: ['#1A4B8C', '#C53030'],
        xaxis: { categories: categorias.value },
        yaxis: { labels: { formatter: ejeCompacto } },
        tooltip: { y: { formatter: (v) => fmtUsd(v) } },
        legend: { position: 'top' },
    };
});
</script>

<template>
    <Head title="Comparador" />

    <section class="bg-white border-b border-gris-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <p class="inline-flex items-center gap-2.5 text-[11px] font-bold uppercase tracking-[0.18em] text-rojo-600 mb-4"><span class="w-7 h-px bg-rojo-500"></span> Herramienta interactiva</p>
            <h1 class="titular-editorial text-4xl sm:text-5xl text-institucional-900">Comparador</h1>
            <p class="text-institucional-500 mt-4 max-w-2xl leading-relaxed text-lg">Compara países, productos o años usando datos reales del portal. Cada selector dispara una nueva consulta a `/api/v1/comparador`.</p>
        </div>
    </section>

    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-6">
        <div class="tarjeta p-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-3">
            <label class="text-xs font-medium text-gris-500">Modo
                <select v-model="estado.modo" class="campo mt-1 py-2 text-sm">
                    <option value="anio">Año vs Año</option>
                    <option value="pais">País A vs País B</option>
                    <option value="producto">Producto A vs Producto B</option>
                </select>
            </label>

            <label class="text-xs font-medium text-gris-500">Flujo
                <select v-model="estado.flujo" class="campo mt-1 py-2 text-sm">
                    <option value="exp">Exportación</option>
                    <option value="imp">Importación</option>
                </select>
            </label>

            <label v-if="estado.modo === 'anio'" class="text-xs font-medium text-gris-500">Dimensión
                <select v-model="estado.dimension" class="campo mt-1 py-2 text-sm">
                    <option value="top-productos">Productos</option>
                    <option value="top-paises">Países</option>
                    <option value="top-departamentos">Departamentos</option>
                </select>
            </label>

            <label v-if="estado.modo === 'anio'" class="text-xs font-medium text-gris-500">Año A
                <select v-model.number="estado.anioA" class="campo mt-1 py-2 text-sm">
                    <option v-for="g in gestiones" :key="g" :value="g">{{ g }}</option>
                </select>
            </label>

            <label v-if="estado.modo === 'anio'" class="text-xs font-medium text-gris-500">Año B
                <select v-model.number="estado.anioB" class="campo mt-1 py-2 text-sm">
                    <option v-for="g in gestiones" :key="g" :value="g">{{ g }}</option>
                </select>
            </label>

            <label v-if="estado.modo === 'pais'" class="text-xs font-medium text-gris-500">País A
                <select v-model.number="estado.paisA" class="campo mt-1 py-2 text-sm">
                    <option :value="null">Selecciona</option>
                    <option v-for="pais in paises" :key="pais.id" :value="pais.id">{{ pais.nombre }}</option>
                </select>
            </label>

            <label v-if="estado.modo === 'pais'" class="text-xs font-medium text-gris-500">País B
                <select v-model.number="estado.paisB" class="campo mt-1 py-2 text-sm">
                    <option :value="null">Selecciona</option>
                    <option v-for="pais in paises" :key="pais.id" :value="pais.id">{{ pais.nombre }}</option>
                </select>
            </label>

            <label v-if="estado.modo === 'producto'" class="text-xs font-medium text-gris-500">Producto A
                <select v-model.number="estado.productoA" class="campo mt-1 py-2 text-sm">
                    <option :value="null">Selecciona</option>
                    <option v-for="producto in productos" :key="producto.id" :value="producto.id">{{ producto.label }}</option>
                </select>
            </label>

            <label v-if="estado.modo === 'producto'" class="text-xs font-medium text-gris-500">Producto B
                <select v-model.number="estado.productoB" class="campo mt-1 py-2 text-sm">
                    <option :value="null">Selecciona</option>
                    <option v-for="producto in productos" :key="producto.id" :value="producto.id">{{ producto.label }}</option>
                </select>
            </label>
        </div>

        <div v-if="error" class="tarjeta p-8 text-center text-negativo">
            No se pudo cargar la comparación: {{ error }}
        </div>

        <ChartCard
            v-else
            :titulo="tituloTarjeta"
            subtitulo="Comparación construida desde la API pública del portal"
            fuente="INE — Bolivia"
            :cargando="cargando"
            :columnas="columnasTabla"
            :filas="filas"
        >
            <template v-if="hayDatos">
                <BaseApexChart
                    :type="estado.modo === 'anio' ? 'bar' : 'line'"
                    :series="series"
                    :opciones="opcionesChart"
                    :height="estado.modo === 'anio' ? Math.max(320, categorias.length * 38) : 340"
                    :cargando="cargando"
                />
            </template>
            <EstadoVacio
                v-else-if="!cargando"
                titulo="Comparación no disponible"
                mensaje="Ajusta los selectores para comparar dos años, dos países o dos productos con datos reales."
            />
        </ChartCard>
    </section>
</template>
