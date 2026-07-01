<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const usuario = computed(() => page.props.auth?.usuario ?? null);
const flash = computed(() => page.props.flash ?? {});
const nombreApp = computed(() => page.props.app?.nombre ?? 'Geodata');

// Menu de navegacion del portal publico.
const navegacion = [
    { titulo: 'Inicio',       ruta: '/' },
    { titulo: 'Explorar',     ruta: '/explorar' },
    { titulo: 'Rankings',     ruta: '/rankings' },
    { titulo: 'Metodologia',  ruta: '/acerca' },
];

const rutaActual = computed(() => page.url);
const menuMovilAbierto = ref(false);

// En la portada el hero es full-screen estilo AJE: el header va transparente
// y flotante encima, volviendose solido al hacer scroll.
const esInicio = computed(() => rutaActual.value === '/' || rutaActual.value.startsWith('/?'));

function esActivo(ruta) {
    if (ruta === '/') return rutaActual.value === '/' || rutaActual.value.startsWith('/?');
    return rutaActual.value.startsWith(ruta);
}

// --- Header auto-ocultable: se esconde al bajar, reaparece al subir ---
const headerVisible = ref(true);
const scrolleado = ref(false);
let ultimoScroll = 0;

function alHacerScroll() {
    const y = window.scrollY;
    scrolleado.value = y > 24;
    if (y > ultimoScroll && y > 90) {
        headerVisible.value = false;        // bajando: ocultar
    } else {
        headerVisible.value = true;         // subiendo: mostrar
    }
    ultimoScroll = y;
}

// Header solido (fondo blanco) salvo en la portada cuando estamos arriba del todo.
const headerSolido = computed(() => !esInicio.value || scrolleado.value);

onMounted(() => window.addEventListener('scroll', alHacerScroll, { passive: true }));
onUnmounted(() => window.removeEventListener('scroll', alHacerScroll));

const anioActual = new Date().getFullYear();
</script>

<template>
    <div class="min-h-full flex flex-col bg-white">
        <!-- ====================== HEADER (fijo, auto-ocultable, transparente sobre el hero) ====================== -->
        <header
            class="fixed top-0 inset-x-0 z-40 transition-all duration-300 ease-out"
            :class="[
                headerVisible ? 'translate-y-0' : '-translate-y-full',
                headerSolido ? 'bg-white/95 backdrop-blur-md border-b border-gris-100 shadow-sm' : 'bg-transparent',
            ]"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6">
                <div class="h-[62px] flex items-center justify-between gap-4">
                    <!-- Logo -->
                    <Link href="/" class="flex items-center gap-2.5 shrink-0">
                        <div class="w-8 h-8 rounded-lg bg-rojo-600 flex items-center justify-center shrink-0">
                            <svg class="w-4.5 h-4.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18"/>
                            </svg>
                        </div>
                        <span class="font-bold text-[17px] tracking-tight transition-colors" :class="headerSolido ? 'text-institucional-900' : 'text-white'">{{ nombreApp }}</span>
                    </Link>

                    <!-- Navegacion (escritorio) -->
                    <nav class="hidden md:flex items-center">
                        <Link
                            v-for="item in navegacion"
                            :key="item.ruta"
                            :href="item.ruta"
                            class="relative px-4 py-[19px] text-sm font-semibold transition-colors"
                            :class="esActivo(item.ruta)
                                ? (headerSolido ? 'text-rojo-600' : 'text-white')
                                : (headerSolido ? 'text-institucional-500 hover:text-institucional-900' : 'text-white/80 hover:text-white')"
                        >
                            {{ item.titulo }}
                            <span
                                v-if="esActivo(item.ruta)"
                                class="absolute left-3 right-3 -bottom-px h-0.5 rounded-full"
                                :class="headerSolido ? 'bg-rojo-600' : 'bg-white'"
                            ></span>
                        </Link>
                    </nav>

                    <!-- Acciones derecha -->
                    <div class="flex items-center gap-2 shrink-0">
                        <a v-if="usuario" href="/admin" class="hidden sm:inline-flex btn btn-secundario text-xs px-4 py-2">Ir al panel</a>
                        <Link v-else href="/acceder" class="hidden sm:inline-flex btn btn-primario text-xs px-4 py-2">Acceder</Link>

                        <button
                            class="md:hidden p-2 rounded-lg transition"
                            :class="headerSolido ? 'text-institucional-500 hover:text-institucional-900 hover:bg-gris-100' : 'text-white hover:bg-white/15'"
                            @click="menuMovilAbierto = !menuMovilAbierto"
                            aria-label="Abrir menu"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Navegacion (movil) -->
            <div v-show="menuMovilAbierto" class="md:hidden border-t border-gris-100 bg-white">
                <nav class="px-4 py-3 space-y-1">
                    <Link
                        v-for="item in navegacion"
                        :key="item.ruta"
                        :href="item.ruta"
                        class="block px-4 py-2.5 rounded-lg text-sm font-semibold transition"
                        :class="esActivo(item.ruta)
                            ? 'bg-rojo-50 text-rojo-600'
                            : 'text-institucional-600 hover:bg-gris-50 hover:text-institucional-900'"
                        @click="menuMovilAbierto = false"
                    >
                        {{ item.titulo }}
                    </Link>
                    <a v-if="usuario" href="/admin" class="btn btn-secundario w-full mt-2">Ir al panel</a>
                    <Link v-else href="/acceder" class="btn btn-primario w-full mt-2">Acceder</Link>
                </nav>
            </div>
        </header>

        <!-- Espaciador: el header es fijo. En la portada NO se reserva (hero full-screen). -->
        <div v-if="!esInicio" class="h-[62px]" aria-hidden="true"></div>

        <!-- Mensajes flash -->
        <div v-if="flash.exito" class="max-w-7xl mx-auto w-full px-4 sm:px-6 mt-4">
            <div class="px-4 py-3 rounded-lg bg-positivo-suave border border-positivo/30 text-positivo text-sm font-medium">
                {{ flash.exito }}
            </div>
        </div>

        <!-- Contenido -->
        <main class="flex-1">
            <slot />
        </main>

        <!-- ====================== FOOTER ====================== -->
        <footer class="bg-institucional-900 text-white/50 mt-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 py-10">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-rojo-600 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18"/>
                            </svg>
                        </div>
                        <div class="leading-tight">
                            <div class="font-bold text-white">{{ nombreApp }}</div>
                            <div class="text-xs text-white/40">Comercio exterior de Bolivia</div>
                        </div>
                    </div>

                    <nav class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                        <Link v-for="item in navegacion" :key="item.ruta" :href="item.ruta" class="hover:text-white transition-colors">
                            {{ item.titulo }}
                        </Link>
                        <a href="https://www.ine.gob.bo" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-rojo-400 hover:text-rojo-300 transition-colors">
                            Fuente: INE
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                        </a>
                    </nav>
                </div>

                <div class="mt-8 pt-6 border-t border-white/10 text-xs text-white/30">
                    (c) {{ anioActual }} {{ nombreApp }} - Datos con fines informativos y educativos.
                </div>
            </div>
        </footer>
    </div>
</template>
