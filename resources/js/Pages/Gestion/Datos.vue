<script setup>
// Pestaña "Datos cargados": historial REAL de carga_archivo (+ último mensaje del
// proceso ETL e incidencias), resumen por fuente y totales INE por gestión.
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import CabeceraGestion from '@/Components/Gestion/CabeceraGestion.vue';

const props = defineProps({
    cargas: Object,
    resumenFuentes: Array,
    totalesIne: Array,
    estados: Object,
});

const page = usePage();
const permisos = computed(() => page.props.auth?.usuario?.permisos ?? []);
const puedeProcesar = computed(() => permisos.value.includes('carga.procesar'));
const puedeCalidad = computed(() => permisos.value.includes('calidad.ver'));

const colorEstado = {
    PENDIENTE: 'bg-gris-200 text-gris-600',
    PROCESANDO: 'bg-blue-100 text-blue-700',
    COMPLETADO: 'bg-emerald-100 text-emerald-700',
    FALLIDO: 'bg-red-100 text-red-700',
};
const ETIQUETA_FLUJO = {
    EXPORTACION: 'Exportación', IMPORTACION: 'Importación', MERCOSUR_PAIS: 'Por país',
    MERCOSUR_ITEM: 'Por ítem', FAOSTAT: 'Índices', ALADI_RANKING: 'Ranking',
};

const nf = new Intl.NumberFormat('es-BO', { maximumFractionDigits: 0 });
const usd = (v) => nf.format(Math.round(v ?? 0));
const fecha = (f) => (f ? new Date(f).toLocaleString('es-BO', { dateStyle: 'short', timeStyle: 'short' }) : '—');

const hayEnCurso = computed(() => (props.estados?.PENDIENTE ?? 0) + (props.estados?.PROCESANDO ?? 0) > 0);

function reintentar(c) {
    router.post(`/admin/cargas/${c.carga_id}/reintentar`, {}, { preserveScroll: true });
}
function eliminar(c) {
    const txt = c.estado === 'COMPLETADO'
        ? `¿Eliminar la carga #${c.carga_id} (${c.nombre_archivo}) y TODAS las filas que insertó (${c.total_filas_validas ?? 0})? Se hace en una transacción y no se puede deshacer.`
        : `¿Eliminar el registro de la carga fallida #${c.carga_id} (${c.nombre_archivo})?`;
    if (!confirm(txt)) return;
    router.delete(`/admin/cargas/${c.carga_id}`, { preserveScroll: true });
}
function actualizar() {
    router.reload({ only: ['cargas', 'estados', 'resumenFuentes', 'totalesIne'] });
}
</script>

<template>
    <Head title="Gestión de datos · Datos cargados" />

    <div class="max-w-7xl mx-auto">
        <CabeceraGestion activa="datos" />

        <div v-if="!cargas.data.length && !resumenFuentes.some(f => f.filas > 0)"
             class="border border-gris-200 bg-gris-50 rounded-md py-6 text-center text-sm font-semibold text-gris-500">
            Aún no hay datos cargados. Ve a <Link href="/admin/cargas/nueva" class="text-[#5e1224] underline">«Cargar datos»</Link>.
        </div>

        <!-- Resumen por fuente -->
        <section class="bg-white border border-gris-200 rounded-md shadow-sm p-5 mb-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-base font-bold text-[#12203d]">Datos en la base, por fuente</h3>
                <a href="/admin/cargas/resumen/exportar" class="px-3 py-1.5 text-xs font-bold border border-gris-200 bg-gris-50 hover:bg-white rounded-sm text-[#12203d]">Descargar Excel</a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3">
                <div v-for="f in resumenFuentes" :key="f.tabla" class="border border-gris-200 rounded-sm p-3 bg-gris-50">
                    <div class="text-[10px] font-bold tracking-widest uppercase text-[#5e1224]">{{ f.sigla }}</div>
                    <div class="text-xl font-extrabold text-[#12203d]">{{ nf.format(f.filas) }}</div>
                    <div class="text-[11px] text-gris-500 leading-tight">{{ f.tabla }}</div>
                    <div class="mt-1 text-[11px] text-gris-600">
                        Gestiones: <b>{{ f.desde ? `${f.desde}–${f.hasta}` : '—' }}</b> · {{ f.cargas }} carga(s)
                    </div>
                </div>
            </div>
        </section>

        <!-- Totales INE por gestión -->
        <section v-if="totalesIne.length" class="bg-white border border-gris-200 rounded-md shadow-sm p-5 mb-4">
            <h3 class="text-base font-bold text-[#12203d] mb-3">INE · totales por gestión (USD)</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-gris-500 text-xs uppercase">
                        <tr>
                            <th class="text-left py-2">Gestión</th>
                            <th class="text-right py-2">Exportaciones FOB</th>
                            <th class="text-right py-2">Importaciones CIF</th>
                            <th class="text-right py-2">Saldo</th>
                            <th class="text-right py-2">Operaciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gris-100">
                        <tr v-for="t in totalesIne" :key="t.gestion">
                            <td class="py-1.5 font-semibold">{{ t.gestion }}</td>
                            <td class="py-1.5 text-right">{{ usd(t.exportaciones) }}</td>
                            <td class="py-1.5 text-right">{{ usd(t.importaciones) }}</td>
                            <td class="py-1.5 text-right font-semibold" :class="t.saldo >= 0 ? 'text-positivo' : 'text-negativo'">{{ usd(t.saldo) }}</td>
                            <td class="py-1.5 text-right text-gris-500">{{ nf.format(t.operaciones) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Historial de cargas -->
        <section class="bg-white border border-gris-200 rounded-md shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3 border-b border-gris-100">
                <h3 class="text-base font-bold text-[#12203d]">Lotes cargados</h3>
                <div class="flex items-center gap-2">
                    <span v-if="hayEnCurso" class="text-xs text-blue-700">Hay cargas en cola o procesándose.</span>
                    <button class="px-3 py-1.5 text-xs font-bold border border-gris-200 rounded-sm hover:bg-gris-50" @click="actualizar">Actualizar</button>
                    <Link href="/admin/cargas/nueva" class="px-3 py-1.5 text-xs font-bold rounded-sm text-white bg-[#5e1224] hover:bg-[#3a0914]">+ Cargar datos</Link>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gris-50 text-gris-600 text-xs">
                        <tr>
                            <th class="text-left px-3 py-2">#</th>
                            <th class="text-left px-3 py-2">Archivo</th>
                            <th class="text-left px-3 py-2">Fuente</th>
                            <th class="text-left px-3 py-2">Flujo</th>
                            <th class="text-center px-3 py-2">Gestión</th>
                            <th class="text-center px-3 py-2">Leídas</th>
                            <th class="text-center px-3 py-2">Válidas</th>
                            <th class="text-center px-3 py-2">Error</th>
                            <th class="text-center px-3 py-2" title="Filas descartadas (sin año o sin código). El detalle está en Calidad.">Omitidas</th>
                            <th class="text-center px-3 py-2">Estado</th>
                            <th class="text-left px-3 py-2">Fecha</th>
                            <th class="text-right px-3 py-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gris-100">
                        <tr v-for="c in cargas.data" :key="c.carga_id" class="hover:bg-gris-50 align-top">
                            <td class="px-3 py-2 text-gris-400">{{ c.carga_id }}</td>
                            <td class="px-3 py-2 font-medium text-gris-800 max-w-[16rem] break-words">{{ c.nombre_archivo }}</td>
                            <td class="px-3 py-2 text-gris-600">{{ c.organizacion?.sigla }}</td>
                            <td class="px-3 py-2 text-xs">{{ ETIQUETA_FLUJO[c.tipo_flujo] ?? c.tipo_flujo }}</td>
                            <td class="px-3 py-2 text-center text-gris-600">{{ c.gestion ?? '—' }}<span v-if="c.mes === 0" class="text-gris-400"> (anual)</span><span v-else-if="c.mes"> / {{ c.mes }}</span></td>
                            <td class="px-3 py-2 text-center">{{ c.total_filas_leidas ?? 0 }}</td>
                            <td class="px-3 py-2 text-center text-emerald-700">{{ c.total_filas_validas ?? 0 }}</td>
                            <td class="px-3 py-2 text-center text-red-600">{{ c.total_filas_error ?? 0 }}</td>
                            <td class="px-3 py-2 text-center text-amber-600">
                                <Link v-if="c.incidencias_count && puedeCalidad" :href="`/admin/calidad/${c.carga_id}`" class="underline" :title="`${c.incidencias_count} incidencia(s) en Calidad`">{{ c.total_filas_omitidas ?? 0 }}</Link>
                                <span v-else>{{ c.total_filas_omitidas ?? 0 }}</span>
                            </td>
                            <td class="px-3 py-2 text-center">
                                <span class="text-xs px-2 py-0.5 rounded" :class="colorEstado[c.estado]" :title="c.ultimo_mensaje || ''">{{ c.estado }}</span>
                                <div v-if="c.ultimo_mensaje && c.estado !== 'PENDIENTE'" class="mt-1 w-56 text-[11px] leading-tight text-left" :class="c.estado === 'FALLIDO' ? 'text-red-600' : 'text-gris-500'">{{ c.ultimo_mensaje }}</div>
                            </td>
                            <td class="px-3 py-2 text-xs text-gris-500 whitespace-nowrap">{{ fecha(c.fecha_carga) }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <template v-if="puedeProcesar">
                                    <button v-if="c.estado === 'FALLIDO' && c.archivo_disponible" class="text-xs font-semibold text-institucional-700 hover:underline mr-2" @click="reintentar(c)">Reintentar</button>
                                    <button v-if="['FALLIDO', 'COMPLETADO'].includes(c.estado)" class="text-xs font-semibold text-red-600 hover:underline" @click="eliminar(c)">Eliminar</button>
                                </template>
                            </td>
                        </tr>
                        <tr v-if="!cargas.data.length">
                            <td colspan="12" class="px-4 py-8 text-center text-gris-400">No hay cargas registradas.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="cargas.last_page > 1" class="flex flex-wrap gap-1 px-5 py-3 border-t border-gris-100">
                <template v-for="l in cargas.links" :key="l.label">
                    <Link v-if="l.url" :href="l.url" preserve-scroll class="px-2.5 py-1 text-xs rounded-sm border"
                          :class="l.active ? 'bg-[#12203d] text-white border-[#12203d]' : 'border-gris-200 hover:bg-gris-50'" v-html="l.label" />
                    <span v-else class="px-2.5 py-1 text-xs text-gris-300" v-html="l.label" />
                </template>
            </div>
        </section>
    </div>
</template>
