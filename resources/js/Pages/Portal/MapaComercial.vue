<script setup>
import { reactive, computed, ref, onMounted, onBeforeUnmount, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import ChartCard from '../../Components/UI/ChartCard.vue';
import EstadoVacio from '../../Components/UI/EstadoVacio.vue';
import { useChartData } from '../../Components/Composables/useChartData.js';
import { fmtCompacto, fmtUsd } from '../../lib/format';

const props = defineProps({
    gestiones: { type: Array, default: () => [] },
});

const estado = reactive({
    gestion: props.gestiones?.[0] ?? null,
    flujo: 'ambos',
});

const { data: flujos, cargando, error } = useChartData('/api/v1/charts/mapa-flujos', () => ({
    gestion: estado.gestion,
    flujo: estado.flujo,
    limit: 25,
}));
const { data: deptos, cargando: cargandoDeptos } = useChartData('/api/v1/charts/top-departamentos', () => ({
    gestion: estado.gestion,
    limit: 9,
}));

const items = computed(() => flujos.value?.items ?? []);
const filasDeptos = computed(() => deptos.value?.items ?? []);

const countryCoords = {
    ARG: [-34.6, -58.4], BRA: [-15.8, -47.9], CHL: [-33.45, -70.66], PER: [-12.04, -77.03], USA: [38.9, -77.03],
    CHN: [39.9, 116.4], JPN: [35.68, 139.69], IND: [28.61, 77.21], ESP: [40.41, -3.7], DEU: [52.52, 13.4],
    GBR: [51.5, -0.12], FRA: [48.85, 2.35], ITA: [41.9, 12.49], NLD: [52.37, 4.89], BEL: [50.85, 4.35],
    COL: [4.71, -74.07], MEX: [19.43, -99.13], PRY: [-25.26, -57.58], URY: [-34.9, -56.19], ECU: [-0.18, -78.47],
    KOR: [37.56, 126.97], CAN: [45.42, -75.69], CHE: [46.95, 7.44], BOL: [-16.5, -68.15],
};

const deptCoords = {
    la_paz: [-16.5, -68.15],
    santa_cruz: [-17.78, -63.18],
    cochabamba: [-17.39, -66.16],
    oruro: [-17.97, -67.11],
    potosi: [-19.58, -65.75],
    tarija: [-21.53, -64.73],
    chuquisaca: [-19.05, -65.26],
    beni: [-14.83, -64.9],
    pando: [-11.03, -68.77],
};

function normalizarDepartamento(nombre) {
    return String(nombre ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/\s+/g, '_');
}

const mapaMundoEl = ref(null);
const mapaBoEl = ref(null);
let mapaMundo = null;
let mapaBo = null;
let capaMundo = null;
let capaBo = null;

function crearMapa(baseEl, centro, zoom) {
    const mapa = L.map(baseEl, { scrollWheelZoom: false }).setView(centro, zoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap',
        maxZoom: 18,
    }).addTo(mapa);
    return mapa;
}

function pintarMundo() {
    if (!mapaMundo) return;
    if (capaMundo) capaMundo.clearLayers();
    capaMundo = L.layerGroup().addTo(mapaMundo);

    items.value.forEach((item) => {
        const coords = countryCoords[item.iso];
        if (!coords) return;

        const valor = estado.flujo === 'exp' ? item.expo : estado.flujo === 'imp' ? item.impo : item.total;
        const color = estado.flujo === 'imp'
            ? '#C53030'
            : estado.flujo === 'exp'
                ? '#38A169'
                : item.expo >= item.impo ? '#38A169' : '#C53030';

        L.circleMarker(coords, {
            radius: Math.max(6, Math.min(20, Math.sqrt(Math.max(valor, 1)) / 140)),
            color,
            fillColor: color,
            fillOpacity: 0.45,
            weight: 2,
        })
            .bindPopup(`
                <strong>${item.label}</strong><br/>
                Exportaciones: ${fmtUsd(item.expo)}<br/>
                Importaciones: ${fmtUsd(item.impo)}<br/>
                Saldo: ${fmtUsd(item.saldo)}
            `)
            .addTo(capaMundo);
    });
}

function pintarBolivia() {
    if (!mapaBo) return;
    if (capaBo) capaBo.clearLayers();
    capaBo = L.layerGroup().addTo(mapaBo);

    filasDeptos.value.forEach((item) => {
        const coords = deptCoords[normalizarDepartamento(item.label)];
        if (!coords) return;

        L.circleMarker(coords, {
            radius: Math.max(5, Math.min(16, Math.sqrt(Math.max(item.valor, 1)) / 200)),
            color: '#1A4B8C',
            fillColor: '#3182CE',
            fillOpacity: 0.45,
            weight: 2,
        })
            .bindPopup(`<strong>${item.label}</strong><br/>Exportaciones: ${fmtUsd(item.valor)}`)
            .addTo(capaBo);
    });
}

onMounted(() => {
    if (mapaMundoEl.value) mapaMundo = crearMapa(mapaMundoEl.value, [0, -20], 2);
    if (mapaBoEl.value) mapaBo = crearMapa(mapaBoEl.value, [-16.7, -64.8], 5);
    pintarMundo();
    pintarBolivia();
});

watch(items, pintarMundo, { deep: true });
watch(filasDeptos, pintarBolivia, { deep: true });

onBeforeUnmount(() => {
    if (mapaMundo) mapaMundo.remove();
    if (mapaBo) mapaBo.remove();
});
</script>

<template>
    <Head title="Mapa Comercial" />

    <section class="bg-white border-b border-gris-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <p class="inline-flex items-center gap-2.5 text-[11px] font-bold uppercase tracking-[0.18em] text-rojo-600 mb-4">
                <span class="w-7 h-px bg-rojo-500"></span> Geografía del comercio
            </p>
            <h1 class="titular-editorial text-4xl sm:text-5xl text-institucional-900">Mapa Comercial</h1>
            <p class="text-institucional-500 mt-4 max-w-2xl leading-relaxed text-lg">Flujos comerciales reales de Bolivia por país y una vista rápida del peso exportador por departamento.</p>
        </div>
    </section>

    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-6">
        <div class="tarjeta p-4 flex flex-wrap items-end gap-3">
            <label class="text-xs font-medium text-gris-500">Año
                <select v-model.number="estado.gestion" class="campo mt-1 py-2 text-sm w-32">
                    <option v-for="g in gestiones" :key="g" :value="g">{{ g }}</option>
                </select>
            </label>
            <label class="text-xs font-medium text-gris-500">Flujo
                <select v-model="estado.flujo" class="campo mt-1 py-2 text-sm w-40">
                    <option value="ambos">Ambos</option>
                    <option value="exp">Exportación</option>
                    <option value="imp">Importación</option>
                </select>
            </label>
            <p class="text-xs text-gris-400 ml-auto">Verde = exportación dominante · rojo = importación dominante.</p>
        </div>

        <div v-if="error" class="tarjeta p-8 text-center text-negativo">
            No se pudieron cargar los flujos del mapa: {{ error }}
        </div>

        <div v-else class="grid grid-cols-1 xl:grid-cols-[1.35fr_0.65fr] gap-6">
            <ChartCard
                titulo="Mapa mundial de flujos"
                subtitulo="Países con mayor relación comercial con Bolivia"
                fuente="INE — Bolivia"
                :cargando="cargando"
                :columnas="[
                    { key: 'label', label: 'País' },
                    { key: 'expo', label: 'Exp.', alinear: 'right', formato: (v) => fmtCompacto(v) },
                    { key: 'impo', label: 'Imp.', alinear: 'right', formato: (v) => fmtCompacto(v) },
                    { key: 'saldo', label: 'Saldo', alinear: 'right', formato: (v) => fmtCompacto(v) }
                ]"
                :filas="items"
            >
                <div v-if="items.length" ref="mapaMundoEl" class="w-full rounded-xl overflow-hidden" style="height: 480px;"></div>
                <EstadoVacio v-else-if="!cargando" titulo="Sin flujos disponibles" mensaje="No hay datos de países para esta combinación de filtros." />
            </ChartCard>

            <div class="space-y-6">
                <ChartCard
                    titulo="Bolivia por departamento"
                    subtitulo="Exportaciones departamentales"
                    fuente="INE — Bolivia"
                    :cargando="cargandoDeptos"
                    :columnas="[
                        { key: 'label', label: 'Departamento' },
                        { key: 'valor', label: 'Valor', alinear: 'right', formato: (v) => fmtCompacto(v) }
                    ]"
                    :filas="filasDeptos"
                >
                    <div v-if="filasDeptos.length" ref="mapaBoEl" class="w-full rounded-xl overflow-hidden" style="height: 280px;"></div>
                    <EstadoVacio v-else-if="!cargandoDeptos" titulo="Sin departamentos disponibles" mensaje="Los datos departamentales aún no están listos para este año." />
                </ChartCard>

                <div class="tarjeta p-5">
                    <h3 class="font-bold text-institucional-900 mb-4">Resumen rápido</h3>
                    <div class="space-y-3">
                        <div v-for="item in items.slice(0, 5)" :key="item.label" class="flex items-center justify-between gap-3 border-b border-gris-100 pb-3 last:border-b-0 last:pb-0">
                            <div>
                                <p class="font-medium text-institucional-900">{{ item.label }}</p>
                                <p class="text-xs text-gris-500">Exp. {{ fmtCompacto(item.expo) }} · Imp. {{ fmtCompacto(item.impo) }}</p>
                            </div>
                            <span class="text-sm font-semibold" :class="item.saldo >= 0 ? 'text-positivo' : 'text-negativo'">
                                {{ fmtCompacto(item.saldo) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
