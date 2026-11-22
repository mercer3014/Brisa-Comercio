<script setup>
/** Modo docente (mejora 4): lista de actividades del docente (el administrador ve todas). */
import { Head, Link } from '@inertiajs/vue3';

defineProps({ actividades: { type: Array, default: () => [] }, puedeCrear: Boolean });
const fecha = (f) => (f ? new Date(f).toLocaleString('es-BO', { dateStyle: 'short', timeStyle: 'short' }) : '—');
</script>

<template>
    <Head title="Actividades docentes" />
    <div class="max-w-6xl mx-auto">
        <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
            <div>
                <h1 class="text-2xl font-bold text-slate-800 mb-1">Actividades para estudiantes</h1>
                <p class="text-slate-500 text-sm">Arma una actividad con datos reales, comparte el código y mira las respuestas de tu curso.</p>
            </div>
            <Link v-if="puedeCrear" href="/admin/actividades/nueva" class="px-4 py-2 rounded bg-marca-700 text-white text-sm font-medium">+ Nueva actividad</Link>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="text-left px-3 py-2 font-medium">Código</th>
                        <th class="text-left px-3 py-2 font-medium">Título</th>
                        <th class="text-left px-3 py-2 font-medium">Nivel</th>
                        <th class="text-right px-3 py-2 font-medium">Preguntas</th>
                        <th class="text-right px-3 py-2 font-medium">Respuestas</th>
                        <th class="text-left px-3 py-2 font-medium">Docente</th>
                        <th class="text-left px-3 py-2 font-medium">Creada</th>
                        <th class="text-left px-3 py-2 font-medium">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="a in actividades" :key="a.actividad_id" class="border-t border-slate-100 hover:bg-slate-50">
                        <td class="px-3 py-2 font-mono font-bold text-marca-700">{{ a.codigo_share }}</td>
                        <td class="px-3 py-2"><Link :href="`/admin/actividades/${a.actividad_id}`" class="text-slate-800 font-medium hover:underline">{{ a.titulo }}</Link></td>
                        <td class="px-3 py-2 capitalize">{{ a.nivel }}</td>
                        <td class="px-3 py-2 text-right">{{ a.preguntas }}</td>
                        <td class="px-3 py-2 text-right">{{ a.participantes }}</td>
                        <td class="px-3 py-2 text-slate-600">{{ a.docente ?? '—' }}</td>
                        <td class="px-3 py-2 text-slate-600">{{ fecha(a.creada_en) }}</td>
                        <td class="px-3 py-2">
                            <span class="px-2 py-0.5 rounded text-xs font-medium" :class="a.activa ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'">{{ a.activa ? 'Abierta' : 'Cerrada' }}</span>
                        </td>
                    </tr>
                    <tr v-if="!actividades.length"><td colspan="8" class="px-3 py-8 text-center text-slate-500">Todavía no hay actividades. {{ puedeCrear ? 'Crea la primera con “Nueva actividad”.' : '' }}</td></tr>
                </tbody>
            </table>
        </div>

        <div class="mt-5 bg-white rounded-xl border border-slate-200 p-4 text-sm text-slate-600">
            <b class="text-slate-800">¿Cómo funciona?</b>
            <ol class="list-decimal pl-5 mt-2 space-y-1">
                <li>Crea la actividad: consigna, enlace a una página del portal ya filtrada y preguntas.</li>
                <li>Las preguntas “con datos” se corrigen solas: la respuesta se calcula en vivo con la base del INE.</li>
                <li>Comparte el código de 6 caracteres. Los estudiantes entran en <span class="font-mono">/aprende</span> o <span class="font-mono">/actividad/CÓDIGO</span>, solo con un alias.</li>
                <li>Revisa aciertos por pregunta, lee las respuestas abiertas y descarga todo en CSV.</li>
            </ol>
        </div>
    </div>
</template>
