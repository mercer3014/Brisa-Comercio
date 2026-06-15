<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    organizaciones: { type: Number, default: 0 },
    estadoBd: { type: String, default: 'desconocido' },
});

const accesos = [
    {
        titulo: 'Explorador',
        desc: 'Filtra y consulta microdatos',
        ruta: '/admin/explorador',
        color: 'bg-blue-50 text-blue-600 group-hover:bg-blue-600 group-hover:text-white',
        icono: 'M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z',
    },
    {
        titulo: 'Dashboards',
        desc: 'Visualiza indicadores clave',
        ruta: '/admin/dashboards',
        color: 'bg-violet-50 text-violet-600 group-hover:bg-violet-600 group-hover:text-white',
        icono: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
    },
    {
        titulo: 'Cargas',
        desc: 'Sube archivos del INE',
        ruta: '/admin/cargas',
        color: 'bg-amber-50 text-amber-600 group-hover:bg-amber-600 group-hover:text-white',
        icono: 'M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5',
    },
    {
        titulo: 'Reportes',
        desc: 'Exporta XLSX, CSV y PDF',
        ruta: '/admin/reportes',
        color: 'bg-rojo-50 text-rojo-600 group-hover:bg-rojo-600 group-hover:text-white',
        icono: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
    },
];
</script>

<template>
    <Head title="Inicio" />

    <div class="max-w-6xl mx-auto">

        <!-- Encabezado -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-institucional-900 tracking-tight">Panel de control</h1>
            <p class="text-gris-500 mt-1 text-sm">Resumen del estado de la plataforma.</p>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-10">
            <!-- Organizaciones -->
            <div class="tarjeta p-5 flex items-start gap-4">
                <span class="kpi-icon bg-rojo-50 text-rojo-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                    </svg>
                </span>
                <div>
                    <p class="text-xs font-semibold text-gris-500 uppercase tracking-wider">Organizaciones</p>
                    <p class="text-3xl font-bold text-institucional-900 mt-1 leading-none">{{ organizaciones }}</p>
                    <p class="text-xs text-gris-400 mt-1.5">fuentes registradas</p>
                </div>
            </div>

            <!-- Base de datos -->
            <div class="tarjeta p-5 flex items-start gap-4">
                <span class="kpi-icon" :class="estadoBd === 'conectada' ? 'bg-positivo-suave text-positivo' : 'bg-negativo-suave text-negativo'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75" />
                    </svg>
                </span>
                <div>
                    <p class="text-xs font-semibold text-gris-500 uppercase tracking-wider">Base de datos</p>
                    <p class="text-3xl font-bold mt-1 leading-none" :class="estadoBd === 'conectada' ? 'text-positivo' : 'text-negativo'">
                        {{ estadoBd === 'conectada' ? 'OK' : 'Error' }}
                    </p>
                    <p class="text-xs text-gris-400 mt-1.5">{{ estadoBd === 'conectada' ? 'Conexión activa' : 'Sin conexión' }}</p>
                </div>
            </div>

            <!-- Estado -->
            <div class="tarjeta p-5 flex items-start gap-4">
                <span class="kpi-icon bg-positivo-suave text-positivo">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
                <div>
                    <p class="text-xs font-semibold text-gris-500 uppercase tracking-wider">Estado</p>
                    <p class="text-3xl font-bold text-institucional-900 mt-1 leading-none">Operativo</p>
                    <p class="text-xs text-gris-400 mt-1.5">todos los servicios activos</p>
                </div>
            </div>
        </div>

        <!-- Accesos rapidos -->
        <div class="mb-2">
            <h2 class="text-xs font-bold uppercase tracking-wider text-gris-400 mb-4">Accesos rapidos</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <Link
                    v-for="a in accesos"
                    :key="a.ruta"
                    :href="a.ruta"
                    class="group tarjeta tarjeta-hover p-5 flex flex-col gap-3"
                >
                    <span class="kpi-icon transition-colors" :class="a.color">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" :d="a.icono" />
                        </svg>
                    </span>
                    <div>
                        <p class="font-bold text-institucional-900 group-hover:text-rojo-700 transition-colors text-sm">{{ a.titulo }}</p>
                        <p class="text-xs text-gris-500 mt-0.5">{{ a.desc }}</p>
                    </div>
                    <svg class="w-4 h-4 text-gris-300 group-hover:text-rojo-500 group-hover:translate-x-0.5 transition-all mt-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                </Link>
            </div>
        </div>

    </div>
</template>
