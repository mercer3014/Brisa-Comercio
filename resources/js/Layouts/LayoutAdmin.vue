<script setup>
import { ref, computed, reactive } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';

const page = usePage();
const usuario = computed(() => page.props.auth?.usuario ?? null);
const flash = computed(() => page.props.flash ?? {});
const nombreApp = computed(() => page.props.app?.nombre ?? 'Geodata');

// Sidebar colapsado (riel de iconos) vs expandido.
const colapsado = ref(false);
// Menu en movil (overlay).
const movilAbierto = ref(false);

const permisos = computed(() => usuario.value?.permisos ?? []);
function puede(permiso) {
    if (!permiso) return true;
    return permisos.value.includes(permiso);
}

// Set de iconos (outline, heroicons-style) por clave.
const ICONOS = {
    inicio:        'M2.25 12l8.954-8.955a1.5 1.5 0 012.122 0L22.5 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75',
    explorador:    'M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z',
    dashboards:    'M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5m.75-9l3-3 2.148 2.148A12.061 12.061 0 0116.5 7.605',
    cargas:        'M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5',
    reportes:      'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
    calidad:       'M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z',
    catalogos:     'M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5',
    organizaciones:'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21',
    perfiles:      'M6 6.878V6a2.25 2.25 0 012.25-2.25h7.5A2.25 2.25 0 0118 6v.878m-12 0c.235-.083.487-.128.75-.128h10.5c.263 0 .515.045.75.128m-12 0A2.25 2.25 0 004.5 9v.878m13.5-3A2.25 2.25 0 0119.5 9v.878m0 0a2.246 2.246 0 00-.75-.128H5.25c-.263 0-.515.045-.75.128m15 0A2.25 2.25 0 0121 12v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6c0-.98.626-1.813 1.5-2.122',
    usuarios:      'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
    roles:         'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z',
    configuracion: 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28z',
    bitacora:      'M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25',
};
const circulo = 'M15 12a3 3 0 11-6 0 3 3 0 016 0z'; // segundo path para configuracion

// Menu agrupado. Cada item: titulo, ruta, icono, permiso.
const grupos = [
    {
        titulo: 'General',
        items: [
            { titulo: 'Inicio',     ruta: '/admin',            icono: 'inicio',     permiso: null },
            { titulo: 'Explorador', ruta: '/admin/explorador', icono: 'explorador', permiso: 'explorador.ver' },
            { titulo: 'Dashboards', ruta: '/admin/dashboards', icono: 'dashboards', permiso: 'dashboard.ver' },
        ],
    },
    {
        titulo: 'Datos',
        items: [
            { titulo: 'Cargas',    ruta: '/admin/cargas',        icono: 'cargas',    permiso: 'carga.ver' },
            { titulo: 'Reportes',  ruta: '/admin/reportes',      icono: 'reportes',  permiso: 'reporte.ver' },
            { titulo: 'Calidad',   ruta: '/admin/calidad',       icono: 'calidad',   permiso: 'calidad.ver' },
            { titulo: 'Catalogos', ruta: '/admin/catalogos/pais',icono: 'catalogos', permiso: 'catalogo.ver' },
        ],
    },
    {
        titulo: 'Administracion',
        items: [
            { titulo: 'Organizaciones', ruta: '/admin/organizaciones', icono: 'organizaciones', permiso: 'organizacion.ver' },
            { titulo: 'Perfiles',       ruta: '/admin/perfiles',       icono: 'perfiles',       permiso: 'perfil.ver' },
            { titulo: 'Usuarios',       ruta: '/admin/usuarios',       icono: 'usuarios',       permiso: 'usuario.ver' },
            { titulo: 'Roles',          ruta: '/admin/roles',          icono: 'roles',          permiso: 'rol.ver' },
            { titulo: 'Configuracion',  ruta: '/admin/configuracion',  icono: 'configuracion',  permiso: 'configuracion.ver' },
            { titulo: 'Bitacora',       ruta: '/admin/bitacora',       icono: 'bitacora',       permiso: 'bitacora.ver' },
        ],
    },
];

// Grupos visibles segun permisos (oculta grupos vacios).
const gruposVisibles = computed(() =>
    grupos
        .map((g) => ({ ...g, items: g.items.filter((i) => puede(i.permiso)) }))
        .filter((g) => g.items.length)
);

// Estado de expansion por grupo (todos abiertos por defecto).
const expandido = reactive(Object.fromEntries(grupos.map((g) => [g.titulo, true])));
function alternarGrupo(titulo) {
    expandido[titulo] = !expandido[titulo];
}

const paises = computed(() => page.props.paises ?? []);
const paisesExpandido = ref(true);

const rutaActual = computed(() => page.url);
function esActivo(ruta) {
    if (ruta === '/admin') return rutaActual.value === '/admin';
    return rutaActual.value.startsWith(ruta);
}

function cerrarSesion() {
    router.post('/logout');
}

const iniciales = computed(() => {
    const n = usuario.value?.nombre_completo ?? '';
    return n.split(' ').filter(Boolean).slice(0, 2).map((p) => p[0]).join('').toUpperCase() || 'U';
});
</script>

<template>
    <div class="h-screen flex bg-gris-50">
        <!-- ===================== SIDEBAR ===================== -->
        <!-- Overlay movil -->
        <div v-if="movilAbierto" class="fixed inset-0 z-30 bg-institucional-950/50 lg:hidden" @click="movilAbierto = false"></div>

        <aside
            class="fixed lg:static inset-y-0 left-0 z-40 flex flex-col bg-institucional-900 text-institucional-100
                   transition-all duration-300 ease-in-out"
            :class="[
                colapsado ? 'lg:w-[72px]' : 'lg:w-64',
                movilAbierto ? 'w-64 translate-x-0' : '-translate-x-full lg:translate-x-0',
            ]"
        >
            <!-- Marca -->
            <!-- Colapsado: logo y boton apilados y centrados (asi el boton nunca se corta). -->
            <div
                class="border-b border-white/10 shrink-0"
                :class="colapsado ? 'flex flex-col items-center gap-2 py-3' : 'h-16 flex items-center gap-2.5 px-4'"
            >
                <Link href="/admin" class="flex items-center gap-2.5 min-w-0">
                    <div class="w-9 h-9 rounded-xl bg-rojo-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18"/>
                        </svg>
                    </div>
                    <span v-if="!colapsado" class="flex flex-col leading-none min-w-0">
                        <span class="font-bold text-base tracking-tight text-white truncate">{{ nombreApp }}</span>
                    </span>
                </Link>
                <button
                    class="hidden lg:flex p-1.5 rounded-md text-institucional-300 hover:bg-white/10 hover:text-white transition"
                    :class="colapsado ? '' : 'ml-auto'"
                    @click="colapsado = !colapsado"
                    :aria-label="colapsado ? 'Expandir menu' : 'Colapsar menu'"
                >
                    <svg class="w-5 h-5 transition-transform" :class="colapsado ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 19.5L8.25 12l7.5-7.5" />
                    </svg>
                </button>
            </div>

            <!-- Navegacion -->
            <nav class="flex-1 overflow-y-auto scrollbar-hidden py-4 px-2.5 space-y-5">
                <div v-for="g in gruposVisibles" :key="g.titulo">
                    <!-- Encabezado de grupo -->
                    <button
                        v-if="!colapsado"
                        class="w-full flex items-center justify-between px-2.5 py-1 mb-1 text-[10px] font-bold uppercase tracking-widest text-institucional-400 hover:text-institucional-200 transition"
                        @click="alternarGrupo(g.titulo)"
                    >
                        {{ g.titulo }}
                        <svg class="w-3 h-3 transition-transform" :class="expandido[g.titulo] ? '' : '-rotate-90'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>
                    <div v-else class="h-px bg-white/10 mx-2 my-1"></div>

                    <!-- Items -->
                    <Transition name="slide-grupo">
                    <div v-show="colapsado || expandido[g.titulo]" class="space-y-0.5">
                        <Link
                            v-for="item in g.items"
                            :key="item.ruta"
                            :href="item.ruta"
                            :title="colapsado ? item.titulo : ''"
                            class="group relative flex items-center gap-3 rounded-lg px-2.5 py-2 text-sm font-medium transition-all"
                            :class="[
                                esActivo(item.ruta)
                                    ? 'bg-rojo-600/90 text-white shadow-sm ring-1 ring-rojo-500/40'
                                    : 'text-institucional-300 hover:bg-white/8 hover:text-white',
                                colapsado ? 'justify-center' : '',
                            ]"
                            @click="movilAbierto = false"
                        >
                            <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" :d="ICONOS[item.icono]" />
                                <path v-if="item.icono === 'configuracion'" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" :d="circulo" />
                            </svg>
                            <span v-if="!colapsado" class="truncate">{{ item.titulo }}</span>
                            <!-- Indicador activo: punto derecho -->
                            <span v-if="esActivo(item.ruta) && !colapsado" class="ml-auto w-1.5 h-1.5 rounded-full bg-white/80"></span>
                        </Link>
                    </div>
                    </Transition>
                </div>

                <!-- Por países -->
                <div v-if="paises.length">
                    <div v-if="!colapsado" class="h-px bg-white/10 mx-0 mb-3"></div>
                    <div v-else class="h-px bg-white/10 mx-2 my-1"></div>

                    <button
                        v-if="!colapsado"
                        class="w-full flex items-center justify-between px-2.5 py-1 mb-1 text-[10px] font-bold uppercase tracking-widest text-institucional-400 hover:text-institucional-200 transition"
                        @click="paisesExpandido = !paisesExpandido"
                    >
                        Por países
                        <svg class="w-3 h-3 transition-transform" :class="paisesExpandido ? '' : '-rotate-90'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    <div v-show="!colapsado && paisesExpandido" class="max-h-56 overflow-y-auto space-y-0.5 pr-0.5">
                        <Link
                            v-for="p in paises"
                            :key="p.id"
                            :href="`/admin/paises/${p.id}`"
                            class="flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-xs font-medium transition-all text-institucional-300 hover:bg-white/8 hover:text-white"
                            @click="movilAbierto = false"
                        >
                            <svg class="w-3.5 h-3.5 shrink-0 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18"/>
                            </svg>
                            <span class="truncate">{{ p.nombre }}</span>
                        </Link>
                    </div>
                </div>
            </nav>

            <!-- Pie del sidebar: ver portal -->
            <div class="p-2.5 border-t border-white/10 shrink-0">
                <a
                    href="/"
                    :title="colapsado ? 'Ver portal' : ''"
                    class="flex items-center gap-3 rounded-md px-2.5 py-2 text-sm font-medium text-institucional-100/80 hover:bg-white/10 hover:text-white transition"
                    :class="colapsado ? 'justify-center' : ''"
                >
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                    </svg>
                    <span v-if="!colapsado" class="truncate">Ver portal</span>
                </a>
            </div>
        </aside>

        <!-- ===================== COLUMNA PRINCIPAL ===================== -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Topbar -->
            <header class="h-16 bg-white border-b border-gris-200 flex items-center px-4 sm:px-6 gap-3 sticky top-0 z-20 shadow-sm">
                <button
                    class="lg:hidden p-2 rounded-lg text-institucional-700 hover:bg-gris-100 transition"
                    @click="movilAbierto = true"
                    aria-label="Abrir menu"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

                <div class="ml-auto flex items-center gap-2 sm:gap-3">
                    <template v-if="usuario">
                        <div class="text-right leading-tight hidden sm:block">
                            <div class="text-sm font-semibold text-institucional-900">{{ usuario.nombre_completo }}</div>
                            <div class="text-xs text-gris-400">{{ (usuario.roles || []).join(', ') || 'Sin rol' }}</div>
                        </div>
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-rojo-600 text-white text-sm font-bold shadow-sm">
                            {{ iniciales }}
                        </span>
                        <div class="w-px h-6 bg-gris-200 hidden sm:block"></div>
                        <button
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg text-gris-600 hover:bg-gris-100 hover:text-negativo transition"
                            @click="cerrarSesion"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                            </svg>
                            <span class="hidden sm:inline">Salir</span>
                        </button>
                    </template>
                </div>
            </header>

            <!-- Area de contenido -->
            <main class="flex-1 overflow-y-auto">
                <div v-if="flash.exito" class="m-4 mb-0 px-4 py-3 rounded-md bg-positivo-suave border border-positivo/30 text-positivo text-sm font-medium">
                    {{ flash.exito }}
                </div>
                <div v-if="flash.error" class="m-4 mb-0 px-4 py-3 rounded-md bg-negativo-suave border border-negativo/30 text-negativo text-sm font-medium">
                    {{ flash.error }}
                </div>

                <div class="p-6">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>

<style scoped>
.slide-grupo-enter-active,
.slide-grupo-leave-active {
    transition: all 0.2s ease;
    overflow: hidden;
}
.slide-grupo-enter-from,
.slide-grupo-leave-to {
    opacity: 0;
    max-height: 0;
}
.slide-grupo-enter-to,
.slide-grupo-leave-from {
    opacity: 1;
    max-height: 500px;
}
</style>
