<script setup>
// Pestaña "Configuración" (diseño del mockup) con el estado REAL del sistema.
// En lugar del respaldo JSON / "borrar datos del navegador" del prototipo, que no
// aplican a un servidor multiusuario, ofrece refrescar vistas y los parámetros.
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import CabeceraGestion from '@/Components/Gestion/CabeceraGestion.vue';

const props = defineProps({
    estado: Object,
    fuentes: Array,
    parametros: Array,
});

const page = usePage();
const permisos = computed(() => page.props.auth?.usuario?.permisos ?? []);
const puedeEditar = computed(() => permisos.value.includes('configuracion.editar'));
const nf = new Intl.NumberFormat('es-BO');
const fecha = (f) => (f ? new Date(f).toLocaleString('es-BO', { dateStyle: 'medium', timeStyle: 'short' }) : '—');

function refrescar() {
    if (confirm('¿Refrescar ahora todas las vistas de resumen? Puede tardar unos minutos con muchos datos.')) {
        router.post('/admin/gestion/refrescar-vistas', {}, { preserveScroll: true });
    }
}
function cerrarSesion() { router.post('/logout'); }
</script>

<template>
    <Head title="Gestión de datos · Configuración" />

    <div class="max-w-7xl mx-auto">
        <CabeceraGestion activa="config" />

        <div class="grid grid-cols-1 lg:grid-cols-[34rem_minmax(0,1fr)] gap-4 items-start">
            <section class="bg-white border border-gris-200 rounded-md shadow-sm p-5">
                <h3 class="text-base font-bold text-[#12203d] mb-4">Configuración</h3>
                <div class="grid grid-cols-2 gap-2">
                    <div class="border border-gris-200 bg-gris-50 rounded-sm p-3">
                        <div class="text-[10px] font-bold tracking-widest uppercase text-gris-500">Última migración</div>
                        <div class="text-xs font-bold text-[#12203d] break-all">{{ estado.migracion ?? '—' }}</div>
                    </div>
                    <div class="border border-gris-200 bg-gris-50 rounded-sm p-3">
                        <div class="text-[10px] font-bold tracking-widest uppercase text-gris-500">Corte de datos (última carga)</div>
                        <div class="text-sm font-bold text-[#12203d]">{{ fecha(estado.corte) }}</div>
                    </div>
                    <div class="border border-gris-200 bg-gris-50 rounded-sm p-3">
                        <div class="text-[10px] font-bold tracking-widest uppercase text-gris-500">Estado de las cifras</div>
                        <div class="text-sm font-bold text-[#12203d]">{{ estado.datos }} ({{ nf.format(estado.filas) }} filas)</div>
                    </div>
                    <div class="border border-gris-200 bg-gris-50 rounded-sm p-3">
                        <div class="text-[10px] font-bold tracking-widest uppercase text-gris-500">Gestiones disponibles</div>
                        <div class="text-sm font-bold text-[#12203d]">{{ estado.gestiones ?? '—' }}</div>
                    </div>
                    <div class="border border-gris-200 bg-gris-50 rounded-sm p-3">
                        <div class="text-[10px] font-bold tracking-widest uppercase text-gris-500">Cargas en cola / procesando</div>
                        <div class="text-sm font-bold text-[#12203d]">{{ estado.pendientes }}<span v-if="estado.trabajos_cola !== null" class="font-normal text-gris-500"> · {{ estado.trabajos_cola }} trabajo(s) en la cola</span></div>
                    </div>
                    <div class="border border-gris-200 bg-gris-50 rounded-sm p-3">
                        <div class="text-[10px] font-bold tracking-widest uppercase text-gris-500">Cargas fallidas</div>
                        <div class="text-sm font-bold" :class="estado.fallidas ? 'text-red-600' : 'text-[#12203d]'">{{ estado.fallidas }}</div>
                    </div>
                </div>
                <p v-if="estado.pendientes && estado.trabajos_cola" class="mt-2 text-[11px] text-amber-700">
                    Hay trabajos esperando: verifique que el worker de colas esté corriendo (<code>composer dev</code> o <code>php artisan queue:work</code>).
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button v-if="puedeEditar" class="px-3 py-1.5 text-xs font-bold border border-gris-200 bg-gris-50 hover:bg-white rounded-sm" @click="refrescar">Refrescar vistas de resumen</button>
                    <a href="/admin/cargas/resumen/exportar" class="px-3 py-1.5 text-xs font-bold border border-gris-200 bg-gris-50 hover:bg-white rounded-sm">Exportar resumen (.xlsx)</a>
                    <Link href="/admin/configuracion" class="px-3 py-1.5 text-xs font-bold border border-gris-200 bg-gris-50 hover:bg-white rounded-sm">Parámetros del sistema</Link>
                    <button class="px-3 py-1.5 text-xs font-bold rounded-sm text-white bg-[#5e1224] hover:bg-[#3a0914]" @click="cerrarSesion">Cerrar sesión</button>
                </div>
            </section>

            <section class="bg-white border border-gris-200 rounded-md shadow-sm p-5">
                <h3 class="text-base font-bold text-[#12203d] mb-3">Datos por fuente</h3>
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase text-gris-500">
                        <tr><th class="text-left py-1.5">Fuente</th><th class="text-left">Tabla</th><th class="text-right">Filas</th><th class="text-center">Gestiones</th><th class="text-right">Última carga</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gris-100">
                        <tr v-for="f in fuentes" :key="f.tabla">
                            <td class="py-1.5 font-semibold">{{ f.sigla }}</td>
                            <td class="text-xs text-gris-500">{{ f.tabla }}</td>
                            <td class="text-right">{{ nf.format(f.filas) }}</td>
                            <td class="text-center whitespace-nowrap">{{ f.desde ? `${f.desde}–${f.hasta}` : '—' }}</td>
                            <td class="text-right text-xs text-gris-500">{{ fecha(f.ultima_carga) }}</td>
                        </tr>
                    </tbody>
                </table>

                <h3 class="text-base font-bold text-[#12203d] mt-5 mb-2">Parámetros</h3>
                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                    <template v-for="p in parametros" :key="p.clave">
                        <dt class="font-mono text-xs text-gris-600">{{ p.clave }}</dt>
                        <dd class="text-gris-800">{{ p.valor }} <span class="text-xs text-gris-400">· {{ p.descripcion }}</span></dd>
                    </template>
                </dl>
            </section>
        </div>
    </div>
</template>
