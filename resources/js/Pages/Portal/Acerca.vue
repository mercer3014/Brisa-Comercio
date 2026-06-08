<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Acerca del portal" />

    <section class="max-w-3xl mx-auto px-4 sm:px-6 py-10">
        <h1 class="text-2xl sm:text-3xl font-bold text-slate-800">Acerca de ComexHub</h1>

        <div class="mt-6 space-y-5 text-slate-600 leading-relaxed">
            <p>
                <strong>ComexHub</strong> es un portal que centraliza y pone a disposicion del publico los
                datos de comercio exterior (exportaciones e importaciones) de Bolivia. Su objetivo es
                responder, de forma clara y visual, preguntas como que exporta el pais, a que destinos,
                que importa y quien lidera cada mercado.
            </p>
            <p>
                Los datos provienen de organizaciones estadisticas oficiales. Actualmente se publica la
                informacion del <strong>Instituto Nacional de Estadistica (INE) de Bolivia</strong>.
                Cada cifra indica su fuente y el periodo al que corresponde.
            </p>
            <p>
                El portal es <strong>abierto y gratuito</strong>: no requiere registro para consultar los
                indicadores, rankings, graficos ni la tabla detallada. El registro solo es necesario para
                el panel de administracion, donde el equipo carga y gestiona los datos.
            </p>
        </div>

        <div class="mt-8 bg-marca-50 border border-marca-100 rounded-xl p-5">
            <p class="text-sm text-marca-800">
                <strong>Nota:</strong> los datos se ofrecen con fines informativos. Para usos oficiales,
                remitase siempre a la fuente original (INE — Bolivia).
            </p>
        </div>

        <div class="mt-8 flex flex-wrap gap-3">
            <Link href="/explorar" class="px-4 py-2 rounded-lg bg-marca-700 text-white text-sm font-medium hover:bg-marca-800">
                Explorar los datos
            </Link>
            <Link href="/rankings" class="px-4 py-2 rounded-lg border border-marca-200 text-marca-700 text-sm font-medium hover:bg-marca-50">
                Ver rankings
            </Link>
        </div>
    </section>
</template>
