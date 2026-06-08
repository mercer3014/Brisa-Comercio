<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const usuario = computed(() => page.props.auth?.usuario ?? null);
const flash = computed(() => page.props.flash ?? {});
const nombreApp = computed(() => page.props.app?.nombre ?? 'ComexHub');

// Menu de navegacion del portal publico.
const navegacion = [
    { titulo: 'Inicio',    ruta: '/' },
    { titulo: 'Explorar',  ruta: '/explorar' },
    { titulo: 'Rankings',  ruta: '/rankings' },
    { titulo: 'Acerca de', ruta: '/acerca' },
];

const rutaActual = computed(() => page.url);
const menuMovilAbierto = ref(false);

function esActivo(ruta) {
    if (ruta === '/') return rutaActual.value === '/' || rutaActual.value.startsWith('/?');
    return rutaActual.value.startsWith(ruta);
}

const anioActual = new Date().getFullYear();
</script>

<template>
    <div class="min-h-full flex flex-col bg-slate-50">
        <!-- Barra superior publica -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-30">
            <div class="max-w-7xl mx-auto px-4 sm:px-6">
                <div class="h-16 flex items-center justify-between">
                    <!-- Logo -->
                    <Link href="/" class="flex items-center gap-2 font-bold text-lg tracking-tight text-marca-800">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-marca-700 text-white font-extrabold">C</span>
                        <span>{{ nombreApp }}</span>
                    </Link>

                    <!-- Navegacion (escritorio) -->
                    <nav class="hidden md:flex items-center gap-1">
                        <Link
                            v-for="item in navegacion"
                            :key="item.ruta"
                            :href="item.ruta"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition"
                            :class="esActivo(item.ruta)
                                ? 'bg-marca-50 text-marca-800'
                                : 'text-slate-600 hover:text-marca-700 hover:bg-slate-50'"
                        >
                            {{ item.titulo }}
                        </Link>
                    </nav>

                    <!-- Acciones derecha -->
                    <div class="flex items-center gap-2">
                        <a
                            v-if="usuario"
                            href="/admin"
                            class="hidden sm:inline-flex px-4 py-2 rounded-lg text-sm font-medium bg-marca-700 text-white hover:bg-marca-800 transition"
                        >
                            Ir al panel
                        </a>
                        <Link
                            v-else
                            href="/acceder"
                            class="hidden sm:inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium text-marca-700 border border-marca-200 hover:bg-marca-50 transition"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                            </svg>
                            Acceder
                        </Link>

                        <!-- Boton menu movil -->
                        <button
                            class="md:hidden p-2 rounded-lg text-slate-600 hover:bg-slate-100"
                            @click="menuMovilAbierto = !menuMovilAbierto"
                            aria-label="Abrir menu"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Navegacion (movil desplegable) -->
            <div v-show="menuMovilAbierto" class="md:hidden border-t border-slate-200 bg-white">
                <nav class="px-4 py-3 space-y-1">
                    <Link
                        v-for="item in navegacion"
                        :key="item.ruta"
                        :href="item.ruta"
                        class="block px-4 py-2.5 rounded-lg text-sm font-medium transition"
                        :class="esActivo(item.ruta)
                            ? 'bg-marca-50 text-marca-800'
                            : 'text-slate-600 hover:bg-slate-50'"
                        @click="menuMovilAbierto = false"
                    >
                        {{ item.titulo }}
                    </Link>
                    <a
                        v-if="usuario"
                        href="/admin"
                        class="block px-4 py-2.5 rounded-lg text-sm font-medium bg-marca-700 text-white text-center"
                    >
                        Ir al panel
                    </a>
                    <Link
                        v-else
                        href="/acceder"
                        class="block px-4 py-2.5 rounded-lg text-sm font-medium text-marca-700 border border-marca-200 text-center"
                    >
                        Acceder
                    </Link>
                </nav>
            </div>
        </header>

        <!-- Mensajes flash -->
        <div v-if="flash.exito" class="max-w-7xl mx-auto w-full px-4 sm:px-6 mt-4">
            <div class="px-4 py-3 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">
                {{ flash.exito }}
            </div>
        </div>

        <!-- Contenido -->
        <main class="flex-1">
            <slot />
        </main>

        <!-- Pie de pagina -->
        <footer class="border-t border-slate-200 bg-white mt-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8 text-sm text-slate-500">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-2 font-semibold text-slate-700">
                        <span class="inline-flex items-center justify-center w-7 h-7 rounded bg-marca-700 text-white text-xs font-extrabold">C</span>
                        {{ nombreApp }}
                    </div>
                    <p>Portal publico de datos de comercio exterior · Fuente: INE — Bolivia.</p>
                </div>
                <p class="mt-4 text-xs text-slate-400">© {{ anioActual }} {{ nombreApp }}. Datos con fines informativos.</p>
            </div>
        </footer>
    </div>
</template>
