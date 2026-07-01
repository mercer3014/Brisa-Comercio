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
const esSeriesMercosur = computed(() => meta.value?.modo === 'series_mercosur');

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

function fmtKg(v) {
    if (v == null) return '—';
    const abs = Math.abs(v);
    if (abs >= 1e9) return `${(v / 1e9).toLocaleString('es-BO', { maximumFractionDigits: 1 })} mil M kg`;
    if (abs >= 1e6) return `${(v / 1e6).toLocaleString('es-BO', { maximumFractionDigits: 1 })} M kg`;
    if (abs >= 1e3) return `${(v / 1e3).toLocaleString('es-BO', { maximumFractionDigits: 1 })} mil kg`;
    return `${v.toLocaleString('es-BO', { maximumFractionDigits: 0 })} kg`;
}

function fmtNum(v) {
    return v == null ? '—' : v.toLocaleString('es-BO', { maximumFractionDigits: 0 });
}

function fmtVar(v) {
    if (v == null) return null;
    const signo = v > 0 ? '+' : '';
    return `${signo}${v.toLocaleString('es-BO', { maximumFractionDigits: 1 })}%`;
}

// --- Iconos coherentes (set outline) por titular ---
const ICONO = {
    producto_exportado:      ['M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5', 'M7.5 9l4.5-4.5m0 0L16.5 9M12 4.5v12'],
    producto_importado:      ['M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5', 'M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3'],
    destino_exportacion:     ['M12 21a9 9 0 100-18 9 9 0 000 18z', 'M3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18'],
    origen_importacion:      ['M12 21a9 9 0 100-18 9 9 0 000 18z', 'M3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18'],
    departamento_exportador: ['M15 10.5a3 3 0 11-6 0 3 3 0 016 0z', 'M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z'],
};
const iconoPaths = (clave) => ICONO[clave] ?? ['M12 6v12m6-6H6'];

// --- Mini graficos de barras horizontales (estilizadas: delgadas, redondeadas) ---
// Set general en navy slate; el Top 1 destacado en crimson premium.
function opcionesBarras(items) {
    const colores = items.map((_, i) => (i === 0 ? '#e11d48' : '#334155'));
    return {
        chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'Plus Jakarta Sans, sans-serif' },
        plotOptions: { bar: { horizontal: true, borderRadius: 6, borderRadiusApplication: 'end', barHeight: '46%', distributed: true } },
        colors: colores,
        dataLabels: { enabled: false },
        legend: { show: false },
        xaxis: {
            categories: items.map((i) => i.label),
            labels: { formatter: (v) => fmtUsd(v), style: { fontSize: '10px', colors: '#94a3b8' } },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: { labels: { style: { fontSize: '11px', colors: '#475569' }, maxWidth: 160 } },
        tooltip: { y: { formatter: (v) => fmtUsd(v) } },
        grid: { strokeDashArray: 4, borderColor: '#f1f5f9', yaxis: { lines: { show: false } } },
    };
}
const serieProductos = computed(() => [{ name: 'Exportado', data: (datos.value.top_productos ?? []).map((i) => Math.round(i.valor)) }]);
const serieDestinos = computed(() => [{ name: 'Exportado', data: (datos.value.top_destinos ?? []).map((i) => Math.round(i.valor)) }]);
const opcProductos = computed(() => opcionesBarras(datos.value.top_productos ?? []));
const opcDestinos = computed(() => opcionesBarras(datos.value.top_destinos ?? []));

// --- Grafico de evolucion mensual (area) ---
const serieEvolucion = computed(() => {
    const ev = datos.value.evolucion ?? [];
    return [
        { name: 'Exportaciones', data: ev.map((e) => Math.round(e.expo)) },
        { name: 'Importaciones', data: ev.map((e) => Math.round(e.impo)) },
    ];
});
const opcEvolucion = computed(() => ({
    chart: { type: 'area', toolbar: { show: false }, height: 340, fontFamily: 'Plus Jakarta Sans, sans-serif' },
    colors: ['#1e293b', '#e11d48'],
    stroke: { curve: 'smooth', width: 2.5 },
    fill: { type: 'gradient', gradient: { opacityFrom: 0.18, opacityTo: 0.01 } },
    dataLabels: { enabled: false },
    xaxis: {
        categories: (datos.value.evolucion ?? []).map((e) => e.periodo ?? MESES[e.mes] ?? e.mes),
        labels: { style: { colors: '#94a3b8' } },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: { labels: { formatter: (v) => fmtUsd(v), style: { colors: '#94a3b8' } } },
    tooltip: { y: { formatter: (v) => fmtUsd(v) } },
    legend: { position: 'top', horizontalAlign: 'left', fontWeight: 600, labels: { colors: '#334155' }, markers: { radius: 12 } },
    grid: { strokeDashArray: 4, borderColor: '#f1f5f9' },
}));

// --- Modos de transporte del hero inmersivo (paneles estilo AJE) ---
const modos = [
    {
        clave: 'maritimo',
        titulo: 'Marítimo',
        descripcion: 'El gran volumen de la carga: contenedores que mueven la balanza comercial del país.',
        img: '/img/brisa/maritimo.png',
        // barco / contenedor
        icono: 'M3 13.5l1.5 5.25a1.5 1.5 0 001.44 1.08h11.12a1.5 1.5 0 001.44-1.08L21 13.5M4.5 13.5h15M5.25 13.5V8.25A1.5 1.5 0 016.75 6.75h10.5a1.5 1.5 0 011.5 1.5v5.25M12 4.5v2.25',
    },
    {
        clave: 'terrestre',
        titulo: 'Terrestre',
        descripcion: 'Rutas y fronteras: el comercio que cruza por carretera con los países vecinos.',
        img: '/img/brisa/terrestre.png',
        // camión
        icono: 'M3 9.75A1.5 1.5 0 014.5 8.25h7.5v7.5H3v-5.25zM12 11.25h3.75l3 3v1.5H12v-4.5zM6.75 18.75a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM16.5 18.75a1.5 1.5 0 100-3 1.5 1.5 0 000 3z',
    },
    {
        clave: 'aerio',
        titulo: 'Aéreo',
        descripcion: 'Valor y velocidad: los bienes de alto valor que viajan por aire hacia el mundo.',
        img: '/img/brisa/aerio.png',
        // avión
        icono: 'M3.75 12l16.5-6-6 16.5-2.25-7.5-8.25-3z',
    },
    {
        clave: 'banderas',
        titulo: 'El mundo',
        descripcion: 'Socios comerciales: los países de destino y origen que conectan a Bolivia con el mundo.',
        img: '/img/brisa/banderas.png',
        // globo terráqueo
        icono: 'M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18',
    },
];
</script>

<template>
    <Head title="Comercio exterior de Bolivia" />

    <!-- ============================ HERO INMERSIVO FULL-SCREEN (ESTILO AJE / BRÚJULA) ============================ -->
    <section class="relative bg-institucional-950 text-white overflow-hidden">
        <h1 class="sr-only">La brújula del comercio exterior de Bolivia — exportaciones, importaciones y balanza comercial por vía marítima, terrestre y aérea.</h1>

        <!-- Velo superior para legibilidad del titular/marca -->
        <div class="pointer-events-none absolute inset-x-0 top-0 z-20 h-44 bg-gradient-to-b from-institucional-950/75 to-transparent"></div>

        <!-- Marca + titular superpuestos (despejando el header fijo) -->
        <div class="pointer-events-none absolute top-24 lg:top-28 left-0 right-0 z-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="inline-flex items-center gap-2.5 rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-[11px] font-bold uppercase tracking-[0.18em] text-white">
                <span class="w-1.5 h-1.5 rounded-full bg-rojo-500"></span>
                Comercio exterior · Bolivia
            </p>
            <p class="titular-editorial mt-4 text-3xl sm:text-5xl lg:text-[3.4rem] text-white max-w-2xl leading-[1.05] [text-shadow:0_2px_18px_rgba(16,32,58,0.5)]">
                La <span class="text-rojo-500">brújula</span> del comercio exterior
            </p>
        </div>

        <!-- Paneles full-bleed: marítimo / terrestre / aéreo -->
        <div class="paneles-transporte">
            <Link
                v-for="m in modos"
                :key="m.clave"
                href="/explorar"
                class="panel-modo group"
                :aria-label="`Explorar comercio por vía ${m.titulo}`"
            >
                <img :src="m.img" :alt="`Vista aérea de transporte ${m.titulo.toLowerCase()}`" class="panel-modo__img" :loading="m.clave === 'maritimo' ? 'eager' : 'lazy'" />
                <div class="panel-modo__velo"></div>

                <!-- Contenido del panel (abajo-izquierda, despejando la barra inferior) -->
                <div class="absolute inset-0 z-10 flex flex-col justify-end p-6 lg:p-8 pb-24 lg:pb-28">
                    <h2 class="titular-editorial text-3xl lg:text-4xl text-white">{{ m.titulo }}</h2>
                    <span class="mt-3 block h-0.5 w-10 bg-rojo-500 rounded-full"></span>
                    <div class="panel-modo__detalle">
                        <p class="mt-3 text-sm text-white/85 leading-relaxed max-w-xs">{{ m.descripcion }}</p>
                    </div>
                    <!-- Botón "+" estilo AJE -->
                    <span class="mt-4 inline-flex w-11 h-11 items-center justify-center rounded-full border border-white/40 bg-white/10 backdrop-blur-sm text-white transition-all group-hover:bg-rojo-600 group-hover:border-rojo-600 group-hover:scale-110">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    </span>
                </div>
            </Link>
        </div>

        <!-- ===== BARRA DE MENÚ INFERIOR (estilo AJE) ===== -->
        <div class="absolute inset-x-0 bottom-0 z-30">
            <div class="relative bg-institucional-950/80 backdrop-blur-md border-t border-white/10">
                <!-- Logo central que sobresale -->
                <Link href="/" class="absolute left-1/2 -translate-x-1/2 -top-5 flex items-center gap-2 rounded-full bg-white shadow-flotante px-4 py-2 ring-1 ring-institucional-900/5">
                    <span class="inline-flex w-7 h-7 items-center justify-center rounded-lg bg-rojo-600">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18"/></svg>
                    </span>
                    <span class="font-bold text-[15px] tracking-tight text-institucional-900">Ovxel</span>
                </Link>

                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
                    <nav class="hidden md:flex items-center gap-7 text-sm font-semibold text-white/80">
                        <Link href="/explorar" class="hover:text-white transition-colors">Explorar</Link>
                        <Link href="/rankings" class="hover:text-white transition-colors">Rankings</Link>
                    </nav>
                    <nav class="hidden md:flex items-center gap-7 text-sm font-semibold text-white/80">
                        <Link href="/acerca" class="hover:text-white transition-colors">Metodología</Link>
                        <Link href="/acceder" class="hover:text-white transition-colors">Acceder</Link>
                    </nav>
                    <!-- Móvil: enlace único centrado bajo el logo -->
                    <Link href="/explorar" class="md:hidden ml-auto inline-flex items-center gap-1.5 text-sm font-semibold text-white">
                        Explorar
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </Link>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================ TIRA DE INDICADORES (con datos) ============================ -->
    <section v-if="hayDatos && ind" class="bg-white border-b border-gris-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="tarjeta p-5">
                <div class="text-[10.5px] font-semibold uppercase tracking-wider text-institucional-400">Exportaciones {{ meta.gestion }}</div>
                <div class="text-[1.55rem] font-bold text-institucional-900 mt-1.5 leading-none">{{ fmtUsd(ind.valor_exportado) }}</div>
                <div v-if="ind.variacion_expo != null" class="text-xs font-semibold mt-2"
                     :class="ind.variacion_expo >= 0 ? 'text-positivo' : 'text-rojo-600'">
                    {{ fmtVar(ind.variacion_expo) }} <span class="text-institucional-400 font-medium">vs {{ ind.gestion_anterior }}</span>
                </div>
            </div>
            <div class="tarjeta p-5">
                <div class="text-[10.5px] font-semibold uppercase tracking-wider text-institucional-400">Importaciones {{ meta.gestion }}</div>
                <div class="text-[1.55rem] font-bold text-institucional-900 mt-1.5 leading-none">{{ fmtUsd(ind.valor_importado) }}</div>
                <div v-if="ind.variacion_impo != null" class="text-xs font-semibold mt-2"
                     :class="ind.variacion_impo >= 0 ? 'text-positivo' : 'text-rojo-600'">
                    {{ fmtVar(ind.variacion_impo) }} <span class="text-institucional-400 font-medium">vs {{ ind.gestion_anterior }}</span>
                </div>
            </div>
            <div class="tarjeta p-5">
                <div class="flex items-center gap-2 text-[10.5px] font-semibold uppercase tracking-wider text-institucional-400">
                    <span class="w-1.5 h-1.5 rounded-full" :class="ind.balanza_comercial >= 0 ? 'bg-positivo' : 'bg-rojo-500'"></span>
                    Balanza comercial
                </div>
                <div class="text-[1.55rem] font-bold mt-1.5 leading-none" :class="ind.balanza_comercial >= 0 ? 'text-positivo' : 'text-rojo-600'">
                    {{ fmtUsd(ind.balanza_comercial) }}
                </div>
                <div class="text-xs font-medium text-institucional-400 mt-1.5">{{ ind.balanza_comercial >= 0 ? 'Superávit' : 'Déficit' }} · {{ meta.gestion }}</div>
            </div>
        </div>
    </section>

    <!-- ============================ BARRA DE FILTROS (minimalista, horizontal) ============================ -->
    <div class="border-y border-gris-100 bg-gris-50/70 backdrop-blur-sm sticky top-[62px] z-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5 flex flex-wrap items-center gap-x-8 gap-y-3">
            <span class="text-[11px] font-bold uppercase tracking-[0.15em] text-institucional-400">Periodo de análisis</span>

            <div class="flex items-center gap-2.5">
                <label class="text-sm font-medium text-institucional-500">Organización</label>
                <select v-model="orgId" class="rounded-lg border border-gris-200 bg-white px-3 py-1.5 text-sm font-semibold text-institucional-800 focus:outline-none focus:border-rojo-500 focus:ring-2 focus:ring-rojo-500/20 transition">
                    <option v-for="o in organizaciones" :key="o.organizacion_id" :value="o.organizacion_id">
                        {{ o.nombre }}{{ o.sigla ? ` (${o.sigla})` : '' }}
                    </option>
                </select>
            </div>

            <div class="flex items-center gap-2.5">
                <label class="text-sm font-medium text-institucional-500">Gestión</label>
                <select v-model="gestion" class="rounded-lg border border-gris-200 bg-white px-3 py-1.5 text-sm font-semibold text-institucional-800 focus:outline-none focus:border-rojo-500 focus:ring-2 focus:ring-rojo-500/20 transition">
                    <option v-for="g in gestiones" :key="g" :value="g">{{ g }}</option>
                    <option v-if="!gestiones.length" :value="null">Sin datos</option>
                </select>
            </div>

            <span v-if="meta.fuente" class="ml-auto inline-flex items-center gap-1.5 text-xs text-institucional-400">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
                {{ meta.fuente }}
            </span>
        </div>
    </div>

    <!-- ============================ CONTENIDO ============================ -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" :class="{ 'opacity-60 pointer-events-none': cargando }">

        <!-- Sin datos -->
        <div v-if="!hayDatos" class="py-20">
            <div class="tarjeta max-w-xl mx-auto p-12 text-center">
                <div class="w-14 h-14 rounded-2xl bg-gris-50 flex items-center justify-center mx-auto mb-5">
                    <svg class="w-7 h-7 text-institucional-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h12M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-12 0L21 3"/></svg>
                </div>
                <p class="titular-editorial text-2xl text-institucional-900">No hay datos publicados</p>
                <p class="text-institucional-500 mt-2 leading-relaxed">
                    Para este periodo aun no hay datos cargados. Selecciona otra gestion en la barra superior.
                </p>
            </div>
        </div>

        <template v-else>
            <!-- INDICADORES KPI -->
            <section v-if="ind" class="py-16">
                <div class="flex items-end justify-between mb-7">
                    <div>
                        <h2 class="titular-editorial text-3xl text-institucional-900">Indicadores de {{ meta.gestion }}</h2>
                        <p class="text-institucional-500 mt-1.5">Las cifras clave del comercio exterior en el periodo.</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                    <div class="tarjeta p-6">
                        <div class="text-xs font-semibold text-institucional-400 uppercase tracking-wide">Valor exportado</div>
                        <div class="text-2xl font-bold text-institucional-900 mt-2 leading-none">{{ fmtUsd(ind.valor_exportado) }}</div>
                        <div v-if="ind.variacion_expo != null" class="text-xs font-semibold mt-2.5"
                             :class="ind.variacion_expo >= 0 ? 'text-positivo' : 'text-rojo-600'">
                            {{ fmtVar(ind.variacion_expo) }} <span class="text-institucional-400 font-medium">vs {{ ind.gestion_anterior }}</span>
                        </div>
                        <div v-else class="text-xs text-institucional-400 mt-2.5">Sin comparativo</div>
                    </div>
                    <div class="tarjeta p-6">
                        <div class="text-xs font-semibold text-institucional-400 uppercase tracking-wide">Valor importado</div>
                        <div class="text-2xl font-bold text-institucional-900 mt-2 leading-none">{{ fmtUsd(ind.valor_importado) }}</div>
                        <div v-if="ind.variacion_impo != null" class="text-xs font-semibold mt-2.5"
                             :class="ind.variacion_impo >= 0 ? 'text-positivo' : 'text-rojo-600'">
                            {{ fmtVar(ind.variacion_impo) }} <span class="text-institucional-400 font-medium">vs {{ ind.gestion_anterior }}</span>
                        </div>
                        <div v-else class="text-xs text-institucional-400 mt-2.5">Sin comparativo</div>
                    </div>
                    <div class="tarjeta p-6">
                        <div class="text-xs font-semibold text-institucional-400 uppercase tracking-wide">Balanza comercial</div>
                        <div class="text-2xl font-bold mt-2 leading-none" :class="ind.balanza_comercial >= 0 ? 'text-positivo' : 'text-rojo-600'">
                            {{ fmtUsd(ind.balanza_comercial) }}
                        </div>
                        <div class="text-xs text-institucional-400 mt-2.5">{{ ind.balanza_comercial >= 0 ? 'Superávit' : 'Déficit' }}</div>
                    </div>
                    <div class="tarjeta p-6">
                        <div class="text-xs font-semibold text-institucional-400 uppercase tracking-wide">Países destino</div>
                        <div class="text-2xl font-bold text-institucional-900 mt-2 leading-none">{{ fmtNum(ind.paises_destino) }}</div>
                        <div class="text-xs text-institucional-400 mt-2.5">de exportación</div>
                    </div>
                    <div class="tarjeta p-6">
                        <div class="text-xs font-semibold text-institucional-400 uppercase tracking-wide">Productos distintos</div>
                        <div class="text-2xl font-bold text-institucional-900 mt-2 leading-none">{{ fmtNum(ind.productos_distintos) }}</div>
                        <div class="text-xs text-institucional-400 mt-2.5">comercializados</div>
                    </div>
                </div>
            </section>

            <!-- LO MAS DESTACADO -->
            <section class="pb-16">
                <h2 class="titular-editorial text-3xl text-institucional-900 mb-7">Lo más destacado</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="t in datos.titulares" :key="t.clave" class="tarjeta tarjeta-hover p-6 flex gap-4 items-start">
                        <span class="shrink-0 inline-flex items-center justify-center w-11 h-11 rounded-xl bg-rojo-50 text-rojo-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path v-for="(d, i) in iconoPaths(t.clave)" :key="i" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" :d="d" />
                            </svg>
                        </span>
                        <div>
                            <p class="text-institucional-800 leading-snug font-medium">{{ t.texto }}</p>
                            <p v-if="t.etiqueta" class="text-xs text-rojo-600 font-semibold mt-2">{{ t.etiqueta }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- RANKINGS DESTACADOS -->
            <section class="pb-16 grid grid-cols-1 lg:grid-cols-2 gap-5">
                <div class="tarjeta p-7">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-lg text-institucional-900 tracking-tight">Top 5 productos exportados</h3>
                        <Link href="/rankings" class="text-rojo-600 text-xs font-semibold hover:text-rojo-700 transition-colors">Ranking completo →</Link>
                    </div>
                    <apexchart v-if="serieProductos[0].data.length" type="bar" height="250" :options="opcProductos" :series="serieProductos" />
                    <p v-else class="text-sm text-institucional-400 py-10 text-center">Sin datos.</p>
                </div>
                <div class="tarjeta p-7">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-lg text-institucional-900 tracking-tight">Top 5 países destino</h3>
                        <Link href="/rankings" class="text-rojo-600 text-xs font-semibold hover:text-rojo-700 transition-colors">Ranking completo →</Link>
                    </div>
                    <apexchart v-if="serieDestinos[0].data.length" type="bar" height="250" :options="opcDestinos" :series="serieDestinos" />
                    <p v-else class="text-sm text-institucional-400 py-10 text-center">Sin datos.</p>
                </div>
            </section>

            <!-- EVOLUCION MENSUAL -->
            <section class="pb-16">
                <div class="tarjeta p-7">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-lg text-institucional-900 tracking-tight">{{ esSeriesMercosur ? 'Evolucion anual' : `Evolucion mensual ${meta.gestion}` }}</h3>
                        <span class="text-xs text-institucional-400">{{ meta.fuente }}</span>
                    </div>
                    <apexchart v-if="serieEvolucion[0].data.length" type="area" height="340" :options="opcEvolucion" :series="serieEvolucion" />
                    <p v-else class="text-sm text-institucional-400 py-10 text-center">Sin datos para graficar.</p>
                </div>
            </section>

            <!-- ACCESOS RAPIDOS (tarjetas claras premium) -->
            <section class="pb-20 grid grid-cols-1 sm:grid-cols-3 gap-5">
                <Link href="/explorar" class="group tarjeta tarjeta-hover p-7 flex flex-col">
                    <span class="kpi-icon w-11 h-11 bg-rojo-50 text-rojo-600 group-hover:bg-rojo-600 group-hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                    </span>
                    <h4 class="font-bold text-lg text-institucional-900 mt-5 group-hover:text-rojo-700 transition-colors">Explorar los datos</h4>
                    <p class="text-sm text-institucional-500 mt-1.5 leading-relaxed">Filtra y descarga el detalle de operaciones.</p>
                    <span class="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-rojo-600">
                        Ir a Explorar
                        <svg class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </span>
                </Link>
                <Link href="/rankings" class="group tarjeta tarjeta-hover p-7 flex flex-col">
                    <span class="kpi-icon w-11 h-11 bg-institucional-50 text-institucional-700 group-hover:bg-institucional-900 group-hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
                    </span>
                    <h4 class="font-bold text-lg text-institucional-900 mt-5 group-hover:text-rojo-700 transition-colors">Rankings y comparadores</h4>
                    <p class="text-sm text-institucional-500 mt-1.5 leading-relaxed">Compara años, productos y países.</p>
                    <span class="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-rojo-600">
                        Ver Rankings
                        <svg class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </span>
                </Link>
                <Link href="/acerca" class="group tarjeta tarjeta-hover p-7 flex flex-col">
                    <span class="kpi-icon w-11 h-11 bg-positivo-suave text-positivo group-hover:bg-positivo group-hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                    </span>
                    <h4 class="font-bold text-lg text-institucional-900 mt-5 group-hover:text-rojo-700 transition-colors">Metodología y fuentes</h4>
                    <p class="text-sm text-institucional-500 mt-1.5 leading-relaxed">Alcance, definiciones y origen de los datos.</p>
                    <span class="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-rojo-600">
                        Leer más
                        <svg class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </span>
                </Link>
            </section>
        </template>
    </div>
</template>
