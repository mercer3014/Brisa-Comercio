<script setup>
// Cabecera común de "Gestión de datos" (diseño del mockup): título + 4 pestañas.
// Cada pestaña es una página Inertia real; se ocultan las que el rol no puede ver.
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    activa: { type: String, required: true }, // cargar | datos | graficos | config
    subtitulo: {
        type: String,
        default: 'Carga las bases oficiales del INE, FAOSTAT, ALADI y MERCOSUR; cada archivo pasa por el ETL (cola) y alimenta el portal y los dashboards.',
    },
});

const page = usePage();
const permisos = computed(() => page.props.auth?.usuario?.permisos ?? []);

const pestanas = [
    { id: 'cargar',   titulo: 'Cargar datos',          ruta: '/admin/cargas/nueva',         permiso: 'carga.crear' },
    { id: 'datos',    titulo: 'Datos cargados',        ruta: '/admin/cargas',               permiso: 'carga.ver' },
    { id: 'graficos', titulo: 'Explorador de gráficos', ruta: '/admin/gestion/graficos',    permiso: 'explorador.ver' },
    { id: 'config',   titulo: 'Configuración',         ruta: '/admin/gestion/configuracion', permiso: 'configuracion.ver' },
];
const visibles = computed(() => pestanas.filter((p) => permisos.value.includes(p.permiso)));
</script>

<template>
    <div class="mb-6">
        <div class="text-[11px] font-bold tracking-[0.18em] uppercase text-[#5e1224]">● Panel administrativo</div>
        <div class="mt-2 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
            <div>
                <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight text-[#12203d]">Gestión de datos de GEODATA</h1>
                <p class="mt-1 text-sm text-gris-500 max-w-3xl">{{ props.subtitulo }}</p>
            </div>
            <nav class="flex flex-wrap gap-1" aria-label="Pestañas de gestión de datos">
                <Link
                    v-for="p in visibles"
                    :key="p.id"
                    :href="p.ruta"
                    class="px-3 py-1.5 text-xs font-bold rounded-sm transition"
                    :class="p.id === props.activa ? 'bg-[#12203d] text-white' : 'text-gris-600 hover:bg-gris-100'"
                >{{ p.titulo }}</Link>
            </nav>
        </div>
    </div>
</template>
