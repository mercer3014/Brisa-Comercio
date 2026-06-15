<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Metodología y Fuentes" />

    <!-- Hero con imagen de fondo -->
    <section class="relative text-white overflow-hidden" style="min-height:420px">
        <div class="absolute inset-0 bg-cover bg-center"
             style="background-image:url('https://images.unsplash.com/photo-1494412574643-ff11b0a5c1c3?auto=format&fit=crop&w=1600&q=80')"></div>
        <!-- Velo navy ligero: imagen nitida, texto legible en la base -->
        <div class="absolute inset-0 bg-gradient-to-t from-institucional-950/92 via-institucional-950/55 to-institucional-900/35"></div>
        <div class="absolute -top-20 -right-20 w-80 h-80 rounded-full bg-rojo-600/15 blur-3xl pointer-events-none"></div>

        <div class="relative max-w-4xl mx-auto px-4 sm:px-6 py-20">
            <span class="pildora bg-white/10 text-white ring-1 ring-white/20 mb-5 inline-flex">
                <span class="w-2 h-2 rounded-full bg-rojo-500 animate-pulse"></span>
                Transparencia de datos
            </span>
            <h1 class="titular-editorial text-4xl sm:text-5xl lg:text-6xl font-black mt-4">
                Metodología<br><span class="subrayado-rojo">y Fuentes</span>
            </h1>
            <p class="text-white/70 text-lg mt-5 max-w-xl leading-relaxed">
                Todo lo que necesitas saber sobre el origen, procesamiento y alcance
                de los datos publicados en Ovxel.
            </p>
        </div>
    </section>

    <!-- Secciones de contenido -->
    <section class="max-w-5xl mx-auto px-4 sm:px-6 py-16 space-y-16">

        <!-- Qué es Ovxel -->
        <div class="grid md:grid-cols-2 gap-10 items-center">
            <div>
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-institucional-900 mb-5">
                    <svg class="w-7 h-7 text-rojo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18"/>
                    </svg>
                </div>
                <h2 class="font-display font-bold text-3xl text-institucional-900 mb-4">¿Qué es Ovxel?</h2>
                <p class="text-gris-600 leading-relaxed text-lg">
                    <strong class="text-institucional-900">Ovxel</strong> centraliza y pone a disposición del
                    público los datos de comercio exterior (exportaciones e importaciones) de Bolivia. Su objetivo
                    es responder, de forma clara y visual, preguntas como: ¿qué exporta el país?, ¿a qué destinos?,
                    ¿qué importa? y ¿quién lidera cada mercado?
                </p>
            </div>
            <div class="rounded-2xl overflow-hidden shadow-xl">
                <img
                    src="https://images.unsplash.com/photo-1488590528505-98d2b5aba04b?auto=format&fit=crop&w=800&q=80"
                    alt="Datos"
                    class="w-full h-56 object-cover"
                />
            </div>
        </div>

        <!-- Cards de pilares -->
        <div class="grid sm:grid-cols-3 gap-6">
            <div class="tarjeta p-7 text-center hover:shadow-flotante transition-shadow">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-rojo-50 mb-4">
                    <svg class="w-6 h-6 text-rojo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/>
                    </svg>
                </div>
                <h3 class="font-bold text-institucional-900 text-lg mb-2">Datos oficiales</h3>
                <p class="text-gris-500 text-sm leading-relaxed">Toda la información proviene del INE Bolivia, fuente estadística oficial del Estado.</p>
            </div>
            <div class="tarjeta p-7 text-center hover:shadow-flotante transition-shadow">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-rojo-50 mb-4">
                    <svg class="w-6 h-6 text-rojo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5"/>
                    </svg>
                </div>
                <h3 class="font-bold text-institucional-900 text-lg mb-2">Visualización clara</h3>
                <p class="text-gris-500 text-sm leading-relaxed">Gráficos interactivos, rankings y tablas para entender el comercio exterior sin ser experto.</p>
            </div>
            <div class="tarjeta p-7 text-center hover:shadow-flotante transition-shadow">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-rojo-50 mb-4">
                    <svg class="w-6 h-6 text-rojo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                    </svg>
                </div>
                <h3 class="font-bold text-institucional-900 text-lg mb-2">Acceso abierto</h3>
                <p class="text-gris-500 text-sm leading-relaxed">Sin registro ni barreras. Consulta indicadores, rankings y la tabla detallada de forma gratuita.</p>
            </div>
        </div>

        <!-- Fuente de datos destacada -->
        <div class="rounded-2xl bg-institucional-900 text-white p-8 md:p-12 flex flex-col md:flex-row gap-8 items-center">
            <div class="flex-1">
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-rojo-400 mb-3">FUENTE PRINCIPAL</p>
                <h2 class="font-display font-bold text-2xl md:text-3xl mb-3">Instituto Nacional de Estadística de Bolivia</h2>
                <p class="text-white/60 leading-relaxed mb-5">
                    Los datos provienen de organizaciones estadísticas oficiales. Actualmente se publica la
                    información del <strong class="text-white">INE Bolivia</strong>. Cada cifra indica su fuente
                    y el período al que corresponde.
                </p>
                <a href="https://www.ine.gob.bo" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-2 text-rojo-400 hover:text-rojo-300 font-semibold transition-colors">
                    www.ine.gob.bo
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                </a>
            </div>
            <div class="shrink-0 w-20 h-20 rounded-2xl bg-rojo-600 flex items-center justify-center">
                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18"/>
                </svg>
            </div>
        </div>

        <!-- Aviso de uso -->
        <div class="rounded-xl border-l-4 border-rojo-600 bg-rojo-50 p-6">
            <div class="flex gap-3 items-start">
                <svg class="w-5 h-5 text-rojo-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
                </svg>
                <p class="text-institucional-800">
                    <strong>Nota:</strong> los datos se ofrecen con fines informativos y educativos. Para usos
                    oficiales, remítase siempre a la fuente original (INE — Bolivia).
                </p>
            </div>
        </div>

        <!-- Info del proyecto + acciones -->
        <div class="grid sm:grid-cols-2 gap-4">
            <div class="tarjeta p-6">
                <h3 class="font-display font-bold text-lg text-institucional-900 mb-3">Institución</h3>
                <p class="text-gris-600 text-sm leading-relaxed">
                    Facultad de Ciencias Económicas y Empresariales (FCEE) — Universidad Autónoma Gabriel
                    René Moreno (UAGRM).
                </p>
            </div>
            <div class="tarjeta p-6">
                <h3 class="font-display font-bold text-lg text-institucional-900 mb-3">Alcance de los datos</h3>
                <p class="text-gris-600 text-sm leading-relaxed">
                    Exportaciones e importaciones anuales de Bolivia por producto, destino, origen y departamento.
                    Datos actualizados por gestión (año).
                </p>
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <Link href="/explorar" class="btn btn-primario">Explorar los datos</Link>
            <Link href="/rankings" class="btn btn-contorno">Ver rankings</Link>
        </div>
    </section>
</template>
