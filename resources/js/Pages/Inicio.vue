<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    organizaciones: { type: Number, default: 0 },
    estadoBd: { type: String, default: 'desconocido' },
});
</script>

<template>
    <Head title="Inicio" />

    <div class="max-w-6xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-800">ComexHub</h1>
            <p class="text-slate-500 mt-1">
                Plataforma de centralizacion y analisis de datos de comercio exterior.
            </p>
        </div>

        <!-- Tarjetas indicadoras -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                <div class="text-sm text-slate-500">Organizaciones registradas</div>
                <div class="text-3xl font-bold text-marca-700 mt-2">{{ organizaciones }}</div>
                <div class="text-xs text-slate-400 mt-1">Hoy: INE Bolivia</div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                <div class="text-sm text-slate-500">Conexion a base de datos</div>
                <div class="text-3xl font-bold mt-2"
                     :class="estadoBd === 'conectada' ? 'text-green-600' : 'text-red-600'">
                    {{ estadoBd === 'conectada' ? 'OK' : 'Error' }}
                </div>
                <div class="text-xs text-slate-400 mt-1">PostgreSQL · esquema public</div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                <div class="text-sm text-slate-500">Estado del prototipo</div>
                <div class="text-3xl font-bold text-marca-700 mt-2">Tarea 1</div>
                <div class="text-xs text-slate-400 mt-1">Entorno y conexion listos</div>
            </div>
        </div>

        <div class="mt-8 bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <h2 class="font-semibold text-slate-700 mb-2">Bienvenido</h2>
            <p class="text-slate-600 text-sm leading-relaxed">
                Este es el punto de partida de <strong>ComexHub</strong>. Desde el menu lateral
                se accederan los modulos de exploracion de microdatos, dashboards, cargas de
                archivos, reportes y administracion, a medida que se desarrollen las siguientes tareas.
            </p>
        </div>
    </div>
</template>
