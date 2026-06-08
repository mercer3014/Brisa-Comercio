<script setup>
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';

const page = usePage();
const usuario = computed(() => page.props.auth?.usuario ?? null);
const flash = computed(() => page.props.flash ?? {});
const nombreApp = computed(() => page.props.app?.nombre ?? 'ComexHub');

const menuAbierto = ref(true);

const permisos = computed(() => usuario.value?.permisos ?? []);

function puede(permiso) {
    if (!permiso) return true;
    return permisos.value.includes(permiso);
}

// Menu lateral del panel privado. Todo el panel vive bajo el prefijo /admin.
// Cada item declara el permiso necesario (null = siempre visible).
const menuCompleto = [
    { titulo: 'Inicio',         ruta: '/admin',                permiso: null },
    { titulo: 'Explorador',     ruta: '/admin/explorador',     permiso: 'explorador.ver' },
    { titulo: 'Dashboards',     ruta: '/admin/dashboards',     permiso: 'dashboard.ver' },
    { titulo: 'Cargas',         ruta: '/admin/cargas',         permiso: 'carga.ver' },
    { titulo: 'Reportes',       ruta: '/admin/reportes',       permiso: 'reporte.ver' },
    { titulo: 'Calidad',        ruta: '/admin/calidad',        permiso: 'calidad.ver' },
    { titulo: 'Catalogos',      ruta: '/admin/catalogos/pais', permiso: 'catalogo.ver' },
    { titulo: 'Organizaciones', ruta: '/admin/organizaciones', permiso: 'organizacion.ver' },
    { titulo: 'Perfiles',       ruta: '/admin/perfiles',       permiso: 'perfil.ver' },
    { titulo: 'Usuarios',       ruta: '/admin/usuarios',       permiso: 'usuario.ver' },
    { titulo: 'Roles',          ruta: '/admin/roles',          permiso: 'rol.ver' },
    { titulo: 'Configuracion',  ruta: '/admin/configuracion',  permiso: 'configuracion.ver' },
    { titulo: 'Bitacora',       ruta: '/admin/bitacora',       permiso: 'bitacora.ver' },
];

const menu = computed(() => menuCompleto.filter((i) => puede(i.permiso)));

const rutaActual = computed(() => page.url);

function esActivo(ruta) {
    if (ruta === '/admin') return rutaActual.value === '/admin';
    return rutaActual.value.startsWith(ruta);
}

function cerrarSesion() {
    router.post('/logout');
}
</script>

<template>
    <div class="min-h-full flex flex-col">
        <!-- Barra superior -->
        <header class="h-14 bg-marca-800 text-white flex items-center px-4 shadow-md z-20">
            <button
                class="p-2 rounded hover:bg-marca-700 mr-2"
                @click="menuAbierto = !menuAbierto"
                aria-label="Alternar menu"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <Link href="/admin" class="flex items-center gap-2 font-bold text-lg tracking-tight">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded bg-white text-marca-800 font-extrabold">C</span>
                {{ nombreApp }}
                <span class="hidden sm:inline text-xs font-normal text-marca-200 ml-1">· Panel</span>
            </Link>

            <div class="ml-auto flex items-center gap-3">
                <a href="/" class="hidden sm:inline text-sm text-marca-200 hover:text-white">Ver portal</a>
                <template v-if="usuario">
                    <div class="text-right leading-tight hidden sm:block">
                        <div class="text-sm font-medium">{{ usuario.nombre_completo }}</div>
                        <div class="text-xs text-marca-200">{{ (usuario.roles || []).join(', ') || 'Sin rol' }}</div>
                    </div>
                    <button
                        class="px-3 py-1.5 text-sm rounded bg-marca-700 hover:bg-marca-600 transition"
                        @click="cerrarSesion"
                    >
                        Salir
                    </button>
                </template>
            </div>
        </header>

        <div class="flex flex-1 min-h-0">
            <!-- Menu lateral -->
            <aside
                v-show="menuAbierto"
                class="w-60 bg-white border-r border-slate-200 flex-shrink-0 overflow-y-auto"
            >
                <nav class="py-4">
                    <Link
                        v-for="item in menu"
                        :key="item.ruta"
                        :href="item.ruta"
                        class="flex items-center gap-3 px-5 py-2.5 text-sm font-medium transition border-l-4"
                        :class="esActivo(item.ruta)
                            ? 'border-marca-600 bg-marca-50 text-marca-800'
                            : 'border-transparent text-slate-600 hover:bg-slate-50 hover:text-marca-700'"
                    >
                        <span class="w-2 h-2 rounded-full"
                              :class="esActivo(item.ruta) ? 'bg-marca-600' : 'bg-slate-300'"></span>
                        {{ item.titulo }}
                    </Link>
                </nav>
            </aside>

            <!-- Area de contenido -->
            <main class="flex-1 overflow-y-auto">
                <!-- Mensajes flash -->
                <div v-if="flash.exito" class="m-4 mb-0 px-4 py-3 rounded bg-green-50 border border-green-200 text-green-800 text-sm">
                    {{ flash.exito }}
                </div>
                <div v-if="flash.error" class="m-4 mb-0 px-4 py-3 rounded bg-red-50 border border-red-200 text-red-800 text-sm">
                    {{ flash.error }}
                </div>

                <div class="p-6">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
