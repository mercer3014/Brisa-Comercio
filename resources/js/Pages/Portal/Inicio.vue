<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    organizaciones: { type: Array, default: () => [] },
    gestiones: { type: Array, default: () => [] },
    organizacionDefecto: { type: Number, default: 1 },
    gestionInicial: { type: Number, default: null },
    portada: { type: Object, default: () => ({}) },
});

const orgId = ref(props.organizacionDefecto);
const gestion = ref(props.gestionInicial ?? props.gestiones?.[0] ?? null);
const datos = ref(props.portada ?? {});
const cargando = ref(false);

const hayDatos = computed(() => datos.value?.meta?.hay_datos);
const meta = computed(() => datos.value?.meta ?? {});
const ind = computed(() => datos.value?.indicadores ?? null);

// Refresca la portada al cambiar organizacion o gestion.
async function refrescar() {
    cargando.value = true;
    try {
        const { data } = await axios.get('/portal/datos', {
            params: { organizacion_id: orgId.value, gestion: gestion.value },
        });
        datos.value = data;
    } finally {
        cargando.value = false;
    }
}

watch([orgId, gestion], refrescar);

// --- Formato ---
const MESES = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

function fmtUsd(v) {
    if (v == null) return '—';
    const abs = Math.abs(v);
    if (abs >= 1e9) return `USD ${(v / 1e9).toLocaleString('es-BO', { maximumFractionDigits: 1 })} mil M`;
    if (abs >= 1e6) return `USD ${(v / 1e6).toLocaleString('es-BO', { maximumFractionDigits: 1 })} M`;
    if (abs >= 1e3) return `USD ${(v / 1e3).toLocaleString('es-BO', { maximumFractionDigits: 1 })} mil`;
    return `USD ${v.toLocaleString('es-BO', { maximumFractionDigits: 0 })}`;
}

function fmtNum(v) {
    return v == null ? '—' : v.toLocaleString('es-BO', { maximumFractionDigits: 0 });
}

function fmtVar(v) {
    if (v == null) return null;
    const signo = v > 0 ? '+' : '';
    return `${signo}${v.toLocaleString('es-BO', { maximumFractionDigits: 1 })}%`;
}

// --- Iconos por titular ---
const ICONO = {
    producto_exportado: '📦',
    producto_importado: '🚢',
    destino_exportacion: '🌎',
    origen_importacion: '🛬',
    departamento_exportador: '📍',
};

// --- Mini graficos de barras horizontales (rankings destacados) ---
function opcionesBarras(items) {
    return {
        chart: { type: 'bar', toolbar: { show: false }, sparkline: { enabled: false } },
        plotOptions: { bar: { horizontal: true, borderRadius: 3, barHeight: '60%' } },
        colors: ['#2563eb'],
        dataLabels: { enabled: false },
        xaxis: {
            categories: items.map((i) => i.label),
            labels: { formatter: (v) => fmtUsd(v), style: { fontSize: '10px' } },
        },
        yaxis: { labels: { style: { fontSize: '11px' }, maxWidth: 160 } },
        tooltip: { y: { formatter: (v) => fmtUsd(v) } },
        grid: { strokeDashArray: 3 },
    };
}
const serieProductos = computed(() => [{ name: 'Exportado', data: (datos.value.top_productos ?? []).map((i) => Math.round(i.valor)) }]);
const serieDestinos = computed(() => [{ name: 'Exportado', data: (datos.value.top_destinos ?? []).map((i) => Math.round(i.valor)) }]);
const opcProductos = computed(() => opcionesBarras(datos.value.top_productos ?? []));
const opcDestinos = computed(() => opcionesBarras(datos.value.top_destinos ?? []));

// --- Grafico de evolucion mensual (linea) ---
const serieEvolucion = computed(() => {
    const ev = datos.value.evolucion ?? [];
    return [
        { name: 'Exportaciones', data: ev.map((e) => Math.round(e.expo)) },
        { name: 'Importaciones', data: ev.map((e) => Math.round(e.impo)) },
    ];
});
const opcEvolucion = computed(() => ({
    chart: { type: 'area', toolbar: { show: false }, height: 300 },
    colors: ['#16a34a', '#dc2626'],
    stroke: { curve: 'smooth', width: 2 },
    fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0.05 } },
    dataLabels: { enabled: false },
    xaxis: { categories: (datos.value.evolucion ?? []).map((e) => MESES[e.mes]) },
    yaxis: { labels: { formatter: (v) => fmtUsd(v) } },
    tooltip: { y: { formatter: (v) => fmtUsd(v) } },
    legend: { position: 'top' },
    grid: { strokeDashArray: 3 },
}));
</script>

<template>
    <Head title="Comercio exterior de Bolivia" />

    <!-- Hero + selector -->
    <section class="bg-gradient-to-br from-marca-800 to-marca-950 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-12 sm:py-16">
            <p class="text-marca-200 text-sm font-medium uppercase tracking-wide">Portal de datos abiertos</p>
            <h1 class="text-3xl sm:text-4xl font-bold mt-3 leading-tight max-w-3xl">
                El comercio exterior de Bolivia, de un vistazo
            </h1>
            <p class="text-marca-100 text-base mt-3 max-w-2xl">
                Que se exporta e importa, a que paises y como evoluciona la balanza comercial.
            </p>

            <div class="mt-7 flex flex-col sm:flex-row gap-3 max-w-xl">
                <div class="flex-1">
                    <label class="block text-xs text-marca-200 mb-1">Organizacion</label>
                    <select v-model="orgId" class="w-full rounded-lg border-0 px-3 py-2.5 text-slate-800 text-sm focus:ring-2 focus:ring-marca-400">
                        <option v-for="o in organizaciones" :key="o.organizacion_id" :value="o.organizacion_id">
                            {{ o.nombre }}{{ o.sigla ? ` (${o.sigla})` : '' }}
                        </option>
                    </select>
                </div>
                <div class="flex-1">
                    <label class="block text-xs text-marca-200 mb-1">Gestion (anio)</label>
                    <select v-model="gestion" class="w-full rounded-lg border-0 px-3 py-2.5 text-slate-800 text-sm focus:ring-2 focus:ring-marca-400">
                        <option v-for="g in gestiones" :key="g" :value="g">{{ g }}</option>
                        <option v-if="!gestiones.length" :value="null">Sin datos</option>
                    </select>
                </div>
            </div>
            <p class="text-marca-300 text-xs mt-3">{{ meta.fuente }}</p>
        </div>
    </section>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-10" :class="{ 'opacity-60 pointer-events-none': cargando }">

        <!-- Sin datos -->
        <div v-if="!hayDatos" class="bg-amber-50 border border-amber-200 rounded-xl p-8 text-center text-amber-800">
            <p class="font-medium text-lg">No hay datos publicados para este periodo.</p>
            <p class="text-sm mt-1">Prueba con otra organizacion o gestion. Cuando se carguen operaciones, veras aqui los titulares e indicadores.</p>
        </div>

        <template v-else>
            <!-- TITULARES AUTOMATICOS -->
            <section class="mb-10">
                <h2 class="text-lg font-bold text-slate-800 mb-4">Lo mas destacado de {{ meta.gestion }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="t in datos.titulares"
                        :key="t.clave"
                        class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 flex gap-4"
                    >
                        <div class="text-3xl">{{ ICONO[t.clave] ?? '•' }}</div>
                        <p class="text-slate-700 text-base leading-snug font-medium">{{ t.texto }}</p>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-3">{{ meta.fuente }}</p>
            </section>

            <!-- INDICADORES GRANDES (KPI) -->
            <section v-if="ind" class="mb-10">
                <h2 class="text-lg font-bold text-slate-800 mb-4">Indicadores del anio</h2>
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                        <div class="text-xs text-slate-500">Valor exportado</div>
                        <div class="text-2xl font-bold text-slate-800 mt-1">{{ fmtUsd(ind.valor_exportado) }}</div>
                        <div v-if="ind.variacion_expo != null"
                             class="text-xs font-medium mt-1"
                             :class="ind.variacion_expo >= 0 ? 'text-green-600' : 'text-red-600'">
                            {{ fmtVar(ind.variacion_expo) }} vs {{ ind.gestion_anterior }}
                        </div>
                        <div v-else class="text-xs text-slate-400 mt-1">Sin comparativo</div>
                    </div>

                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                        <div class="text-xs text-slate-500">Valor importado</div>
                        <div class="text-2xl font-bold text-slate-800 mt-1">{{ fmtUsd(ind.valor_importado) }}</div>
                        <div v-if="ind.variacion_impo != null"
                             class="text-xs font-medium mt-1"
                             :class="ind.variacion_impo >= 0 ? 'text-green-600' : 'text-red-600'">
                            {{ fmtVar(ind.variacion_impo) }} vs {{ ind.gestion_anterior }}
                        </div>
                        <div v-else class="text-xs text-slate-400 mt-1">Sin comparativo</div>
                    </div>

                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                        <div class="text-xs text-slate-500">Balanza comercial</div>
                        <div class="text-2xl font-bold mt-1"
                             :class="ind.balanza_comercial >= 0 ? 'text-green-600' : 'text-red-600'">
                            {{ fmtUsd(ind.balanza_comercial) }}
                        </div>
                        <div class="text-xs text-slate-400 mt-1">{{ ind.balanza_comercial >= 0 ? 'Superavit' : 'Deficit' }}</div>
                    </div>

                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                        <div class="text-xs text-slate-500">Paises destino</div>
                        <div class="text-2xl font-bold text-marca-700 mt-1">{{ fmtNum(ind.paises_destino) }}</div>
                        <div class="text-xs text-slate-400 mt-1">de exportacion</div>
                    </div>

                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                        <div class="text-xs text-slate-500">Productos distintos</div>
                        <div class="text-2xl font-bold text-marca-700 mt-1">{{ fmtNum(ind.productos_distintos) }}</div>
                        <div class="text-xs text-slate-400 mt-1">comercializados</div>
                    </div>
                </div>
            </section>

            <!-- RANKINGS DESTACADOS -->
            <section class="mb-10 grid grid-cols-1 lg:grid-cols-2 gap-5">
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="font-semibold text-slate-800">Top 5 productos exportados</h3>
                        <Link href="/rankings" class="text-marca-700 text-xs font-medium hover:underline">Ver ranking completo →</Link>
                    </div>
                    <apexchart v-if="serieProductos[0].data.length" type="bar" height="240" :options="opcProductos" :series="serieProductos" />
                    <p v-else class="text-sm text-slate-400 py-8 text-center">Sin datos.</p>
                </div>

                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="font-semibold text-slate-800">Top 5 paises destino</h3>
                        <Link href="/rankings" class="text-marca-700 text-xs font-medium hover:underline">Ver ranking completo →</Link>
                    </div>
                    <apexchart v-if="serieDestinos[0].data.length" type="bar" height="240" :options="opcDestinos" :series="serieDestinos" />
                    <p v-else class="text-sm text-slate-400 py-8 text-center">Sin datos.</p>
                </div>
            </section>

            <!-- EVOLUCION MENSUAL -->
            <section class="mb-6 bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                <h3 class="font-semibold text-slate-800 mb-2">Evolucion mensual {{ meta.gestion }}</h3>
                <apexchart v-if="serieEvolucion[0].data.length" type="area" height="300" :options="opcEvolucion" :series="serieEvolucion" />
                <p v-else class="text-sm text-slate-400 py-8 text-center">Sin datos mensuales.</p>
                <p class="text-xs text-slate-400 mt-2">{{ meta.fuente }}</p>
            </section>

            <!-- Accesos -->
            <section class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <Link href="/explorar" class="group bg-marca-50 rounded-xl border border-marca-100 p-5 hover:bg-marca-100 transition">
                    <h4 class="font-bold text-marca-800">Explorar los datos</h4>
                    <p class="text-sm text-slate-600 mt-1">Filtra y descarga el detalle.</p>
                </Link>
                <Link href="/rankings" class="group bg-marca-50 rounded-xl border border-marca-100 p-5 hover:bg-marca-100 transition">
                    <h4 class="font-bold text-marca-800">Rankings y comparadores</h4>
                    <p class="text-sm text-slate-600 mt-1">Compara anios, productos y paises.</p>
                </Link>
                <Link href="/acerca" class="group bg-marca-50 rounded-xl border border-marca-100 p-5 hover:bg-marca-100 transition">
                    <h4 class="font-bold text-marca-800">Acerca del portal</h4>
                    <p class="text-sm text-slate-600 mt-1">Fuente y alcance de los datos.</p>
                </Link>
            </section>
        </template>
    </div>
</template>
