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

// Coordenadas por pais_id (catalogo `pais`), no por ISO: la columna iso_alpha3
// de la base de datos esta vacia para todos los registros, asi que el cruce
// por codigo ISO nunca encontraba nada y el mapa mundial salia sin marcadores.
// El pais_id es estable y ya viene en la respuesta de /api/v1/charts/mapa-flujos.
const countryCoords = {
    96: [34.53, 69.17], 218: [60.1, 19.95], 168: [41.33, 19.82], 8: [52.52, 13.4], 195: [42.51, 1.52],
    80: [-8.84, 13.23], 180: [18.22, -63.05], 185: [-75, -15], 233: [-75, -15], 68: [17.12, -61.85],
    181: [12.11, -68.93], 19: [24.71, 46.68], 123: [36.75, 3.06], 9: [-34.6, -58.4], 220: [40.18, 44.51],
    156: [12.52, -70.03], 39: [-35.28, 149.13], 58: [48.21, 16.37], 128: [40.41, 49.87], 109: [25.06, -77.35],
    110: [26.23, 50.59], 61: [23.81, 90.41], 95: [13.1, -59.62], 20: [50.85, 4.35], 21: [17.25, -88.77],
    163: [6.5, 2.6], 37: [32.29, -64.78], 135: [53.9, 27.57], 228: [-16.5, -68.15], 178: [43.86, 18.41],
    208: [-24.63, 25.9], 243: [-54.4, 3.35], 10: [-15.8, -47.9], 132: [4.94, 114.94], 111: [42.7, 23.32],
    201: [12.37, -1.52], 214: [-3.42, 29.93], 196: [27.47, 89.64], 148: [14.93, -23.51], 190: [19.29, -81.38],
    147: [11.56, 104.92], 76: [3.87, 11.52], 11: [45.42, -75.69], 194: [6.93, 79.85], 193: [12.13, 15.06],
    51: [50.09, 14.42], 12: [-33.45, -70.66], 66: [39.9, 116.4], 46: [35.19, 33.38], 207: [-12.19, 96.87],
    35: [4.71, -74.07], 188: [-11.7, 43.26], 131: [-4.27, 15.24], 242: [-21.2, -159.78], 174: [39.03, 125.75],
    38: [37.56, 126.97], 142: [6.83, -5.29], 49: [9.93, -84.08], 108: [45.81, 15.98], 98: [23.13, -82.38],
    177: [12.11, -68.93], 44: [55.68, 12.57], 122: [11.57, 43.15], 146: [15.3, -61.39], 13: [-0.18, -78.47],
    79: [30.04, 31.24], 42: [13.69, -89.22], 88: [24.47, 54.37], 211: [15.32, 38.93], 171: [48.15, 17.11],
    101: [46.06, 14.51], 22: [40.42, -3.7], 14: [38.9, -77.03], 154: [59.44, 24.75], 82: [9.03, 38.74],
    398: [-51.7, -57.85], 399: [62.01, -6.77], 215: [-18.14, 178.44], 72: [14.6, 120.98], 50: [60.17, 24.94],
    18: [48.85, 2.35], 126: [0.39, 9.45], 170: [13.45, -16.58], 139: [41.72, 44.79], 229: [-54.28, -36.5],
    104: [5.6, -0.19], 231: [36.14, -5.35], 197: [12.05, -61.75], 106: [37.98, 23.73], 94: [64.18, -51.69],
    87: [16.0, -61.73], 198: [13.47, 144.75], 73: [14.63, -90.51], 84: [4.93, -52.33], 244: [49.46, -2.54],
    133: [9.64, -13.58], 213: [11.86, -15.6], 173: [3.75, 8.78], 149: [6.8, -58.16], 144: [18.59, -72.31],
    395: [-53.1, 73.5], 85: [14.1, -87.22], 62: [22.32, 114.17], 63: [47.5, 19.04], 23: [28.61, 77.21],
    75: [-6.21, 106.85], 129: [33.31, 44.36], 199: [35.69, 51.39], 92: [53.35, -6.26], 400: [54.15, -4.48],
    137: [64.15, -21.94], 83: [31.77, 35.21], 24: [41.9, 12.49], 99: [17.97, -76.79], 25: [35.68, 139.69],
    246: [49.19, -2.11], 153: [31.96, 35.94], 124: [51.17, 71.45], 90: [-1.29, 36.82], 158: [42.87, 74.59],
    250: [1.45, 173.0], 240: [42.66, 21.17], 56: [29.38, 47.98], 117: [17.97, 102.6], 222: [-29.31, 27.48],
    97: [56.95, 24.11], 113: [33.89, 35.5], 155: [6.3, -10.8], 120: [32.89, 13.19], 217: [47.14, 9.52],
    91: [54.69, 25.28], 161: [49.61, 6.13], 93: [22.2, 113.55], 205: [41.99, 21.43], 169: [-18.88, 47.51],
    36: [3.14, 101.69], 179: [-13.96, 33.79], 237: [4.17, 73.51], 183: [12.65, -8.0], 136: [35.9, 14.51],
    59: [34.02, -6.83], 245: [7.1, 171.38], 112: [14.6, -61.06], 115: [-20.16, 57.5], 157: [18.09, -15.98],
    249: [-12.78, 45.23], 26: [19.43, -99.13], 394: [6.92, 158.16], 202: [28.2, -177.37], 65: [47.01, 28.86],
    116: [43.74, 7.42], 212: [47.89, 106.91], 184: [42.44, 19.26], 236: [16.79, -62.19], 127: [-25.97, 32.57],
    55: [19.76, 96.08], 165: [-22.56, 17.08], 225: [-0.55, 166.92], 247: [-10.45, 105.68], 140: [27.72, 85.32],
    74: [12.11, -86.24], 119: [13.51, 2.11], 60: [9.08, 7.4], 224: [-19.06, -169.92], 248: [-29.03, 167.95],
    43: [59.91, 10.75], 134: [-22.26, 166.45], 52: [-41.29, 174.78], 27: [23.61, 58.59], 232: [0, -170],
    28: [52.37, 4.89], 69: [33.68, 73.05], 234: [7.5, 134.62], 47: [8.98, -79.52], 162: [-9.48, 147.15],
    15: [-25.26, -57.58], 16: [-12.04, -77.03], 209: [-25.07, -130.1], 40: [-17.53, -149.57], 64: [52.23, 21.01],
    41: [38.72, -9.14], 29: [18.47, -66.11], 70: [25.29, 51.53], 30: [51.5, -0.12], 182: [4.39, 18.56],
    100: [50.09, 14.42], 150: [-4.32, 15.31], 81: [18.49, -69.93], 118: [-20.88, 55.45], 200: [-1.94, 30.06],
    53: [44.43, 26.1], 71: [55.75, 37.62], 241: [27.15, -13.2], 172: [-9.43, 159.95], 235: [-13.83, -171.77],
    191: [-14.28, -170.7], 210: [17.3, -62.73], 397: [13.16, -61.22], 167: [17.9, -62.83], 160: [43.94, 12.46],
    239: [46.78, -56.18], 203: [-15.93, -5.72], 86: [14.01, -60.99], 219: [41.9, 12.45], 192: [0.33, 6.73],
    151: [14.72, -17.47], 114: [44.79, 20.45], 187: [44.79, 20.45], 164: [-4.62, 55.45], 204: [8.48, -13.23],
    45: [1.35, 103.82], 54: [33.51, 36.28], 238: [2.05, 45.32], 125: [6.93, 79.85], 48: [-25.75, 28.19],
    186: [15.5, 32.56], 393: [4.85, 31.58], 31: [59.33, 18.07], 32: [46.95, 7.44], 143: [5.87, -55.17],
    216: [78.22, 15.65], 176: [-26.32, 31.13], 391: [38.54, 68.78], 57: [13.75, 100.5], 67: [25.03, 121.57],
    77: [-6.16, 35.75], 189: [-75, -15], 230: [-7.31, 72.41], 221: [31.9, 35.2], 152: [-8.56, 125.57],
    141: [6.13, 1.22], 223: [-9.2, -171.85], 396: [-21.14, -175.2], 78: [10.65, -61.52], 145: [36.81, 10.18],
    206: [21.47, -71.14], 175: [37.95, 58.38], 89: [39.93, 32.86], 392: [-8.52, 179.2], 107: [50.45, 30.52],
    121: [0.35, 32.58], 33: [-34.9, -56.19], 159: [41.31, 69.28], 226: [-17.73, 168.32], 34: [10.48, -66.9],
    105: [21.03, 105.85], 130: [18.43, -64.62], 227: [18.34, -64.93], 251: [-13.28, -176.17], 166: [15.37, 44.19],
    138: [-15.39, 28.32], 103: [-17.83, 31.05],
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
        const coords = countryCoords[item.pais_id];
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
                <div class="relative">
                    <div ref="mapaMundoEl" class="w-full rounded-xl overflow-hidden" style="height: 480px;"></div>
                    <div v-if="!cargando && !items.length" class="absolute inset-0 bg-white rounded-xl">
                        <EstadoVacio titulo="Sin flujos disponibles" mensaje="No hay datos de países para esta combinación de filtros." />
                    </div>
                </div>
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
                    <div class="relative">
                        <div ref="mapaBoEl" class="w-full rounded-xl overflow-hidden" style="height: 280px;"></div>
                        <div v-if="!cargandoDeptos && !filasDeptos.length" class="absolute inset-0 bg-white rounded-xl">
                            <EstadoVacio titulo="Sin departamentos disponibles" mensaje="Los datos departamentales aún no están listos para este año." />
                        </div>
                    </div>
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
