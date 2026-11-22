<script setup>
/** Modo docente (mejora 4): detalle, código para compartir, aciertos y respuestas. */
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    actividad: { type: Object, required: true },
    url: { type: String, default: '' },
    participantes: { type: Number, default: 0 },
    preguntas: { type: Array, default: () => [] },
    filas: { type: Array, default: () => [] },
    puedeCrear: Boolean,
    pestana: { type: String, default: 'resumen' },
});
const copiado = ref(false);
async function copiar() {
    try { await navigator.clipboard.writeText(props.url); copiado.value = true; setTimeout(() => (copiado.value = false), 1500); } catch { /* sin portapapeles */ }
}
function alternar() { router.patch(`/admin/actividades/${props.actividad.actividad_id}/estado`, {}, { preserveScroll: true }); }
const fecha = (f) => (f ? new Date(f).toLocaleString('es-BO', { dateStyle: 'short', timeStyle: 'short' }) : '—');
const TIPO = { dato: 'Con datos', opcion: 'Opción múltiple', abierta: 'Abierta' };
const base = `/admin/actividades/${props.actividad.actividad_id}`;
</script>

<template>
    <Head :title="`${actividad.titulo} · Actividades`" />
    <div class="max-w-6xl mx-auto">
        <Link href="/admin/actividades" class="text-sm text-slate-500 hover:underline">← Actividades</Link>
        <div class="flex flex-wrap items-start justify-between gap-3 mt-1 mb-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">{{ actividad.titulo }}</h1>
                <p class="text-slate-500 text-sm">Nivel {{ actividad.nivel }} · creada {{ fecha(actividad.creada_en) }}<template v-if="actividad.cierra_en"> · cierra {{ fecha(actividad.cierra_en) }}</template></p>
            </div>
            <div class="flex items-center gap-2">
                <span class="px-2 py-1 rounded text-xs font-medium" :class="actividad.activa ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'">{{ actividad.activa ? 'Abierta' : 'Cerrada' }}</span>
                <button v-if="puedeCrear" class="px-3 py-1.5 rounded bg-slate-200 text-sm" @click="alternar">{{ actividad.activa ? 'Cerrar actividad' : 'Reabrir' }}</button>
            </div>
        </div>

        <div class="grid md:grid-cols-3 gap-4 mb-4">
            <div class="bg-white rounded-xl border border-slate-200 p-4 md:col-span-2">
                <div class="text-xs text-slate-500 uppercase font-medium mb-1">Comparte con tus estudiantes</div>
                <div class="flex flex-wrap items-center gap-4">
                    <span class="font-mono text-4xl font-bold tracking-widest text-marca-700">{{ actividad.codigo_share }}</span>
                    <div class="text-sm">
                        <div class="font-mono text-slate-700 break-all">{{ url }}</div>
                        <button class="mt-1 px-2 py-1 rounded bg-slate-200 text-xs" @click="copiar">{{ copiado ? '¡Copiado!' : 'Copiar enlace' }}</button>
                        <a :href="url" target="_blank" rel="noopener" class="ml-2 text-xs text-marca-700 hover:underline">Ver como estudiante ↗</a>
                    </div>
                </div>
                <p class="text-xs text-slate-500 mt-2">También pueden escribir el código en el Panel Educación (/aprende).</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <div class="text-xs text-slate-500 uppercase font-medium mb-1">Respuestas recibidas</div>
                <div class="text-4xl font-bold text-slate-800">{{ participantes }}</div>
                <a :href="`${base}/respuestas?formato=csv`" class="inline-block mt-2 text-sm text-marca-700 hover:underline">Descargar CSV</a>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-4 mb-4 text-sm">
            <div class="text-xs text-slate-500 uppercase font-medium mb-1">Consigna</div>
            <p class="whitespace-pre-line text-slate-700">{{ actividad.consigna }}</p>
            <a v-if="actividad.configuracion?.enlace" :href="actividad.configuracion.enlace" target="_blank" rel="noopener" class="inline-block mt-2 text-marca-700 font-mono text-xs hover:underline">{{ actividad.configuracion.enlace }} ↗</a>
        </div>

        <div class="flex gap-1 border-b border-slate-200 mb-4">
            <Link :href="base" class="px-4 py-2 text-sm -mb-px border-b-2" :class="pestana === 'resumen' ? 'border-marca-700 text-marca-700 font-medium' : 'border-transparent text-slate-500'">Resumen por pregunta</Link>
            <Link :href="`${base}/respuestas`" class="px-4 py-2 text-sm -mb-px border-b-2" :class="pestana === 'respuestas' ? 'border-marca-700 text-marca-700 font-medium' : 'border-transparent text-slate-500'">Respuestas por estudiante</Link>
        </div>

        <div v-if="pestana === 'resumen'" class="space-y-3">
            <div v-for="p in preguntas" :key="p.pregunta_id" class="bg-white rounded-xl border border-slate-200 p-4">
                <div class="flex flex-wrap justify-between gap-2">
                    <div class="font-medium text-slate-800">{{ p.orden }}. {{ p.enunciado }}</div>
                    <span class="text-xs px-2 py-0.5 rounded bg-slate-100 text-slate-600 h-fit">{{ TIPO[p.tipo] }}</span>
                </div>
                <p v-if="p.esperada" class="text-sm text-slate-600 mt-1">Respuesta esperada: <b>{{ p.esperada }}</b></p>
                <div v-if="p.tipo !== 'abierta'" class="mt-2">
                    <div class="flex justify-between text-xs text-slate-500 mb-1"><span>{{ p.correctas }} de {{ p.respondidas }} correctas</span><span>{{ p.pct ?? 0 }}%</span></div>
                    <div class="h-2 bg-slate-100 rounded"><div class="h-2 rounded bg-emerald-500" :style="`width:${p.pct ?? 0}%`"></div></div>
                </div>
                <ul v-else class="mt-2 space-y-1 text-sm">
                    <li v-for="(a, k) in p.abiertas" :key="k" class="border-l-2 border-slate-200 pl-2"><b>{{ a.alias }}:</b> {{ a.valor }}</li>
                    <li v-if="!p.abiertas.length" class="text-slate-400">Sin respuestas todavía.</li>
                </ul>
            </div>
        </div>

        <div v-else class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="text-left px-3 py-2 font-medium">Alias</th>
                        <th class="text-left px-3 py-2 font-medium">Enviado</th>
                        <th class="text-right px-3 py-2 font-medium">Aciertos</th>
                        <th v-for="p in preguntas" :key="p.pregunta_id" class="text-left px-3 py-2 font-medium" :title="p.enunciado">P{{ p.orden }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(f, k) in filas" :key="k" class="border-t border-slate-100 align-top">
                        <td class="px-3 py-2 font-medium">{{ f.alias }}</td>
                        <td class="px-3 py-2 text-slate-500 whitespace-nowrap">{{ fecha(f.enviado) }}</td>
                        <td class="px-3 py-2 text-right">{{ f.aciertos }}</td>
                        <td v-for="(r, j) in f.respuestas" :key="j" class="px-3 py-2 max-w-xs"
                            :class="r.correcta === true ? 'text-emerald-700' : r.correcta === false ? 'text-red-600' : 'text-slate-700'">
                            {{ r.valor || '—' }}<span v-if="r.correcta !== null && r.correcta !== undefined"> {{ r.correcta ? '✓' : '✗' }}</span>
                        </td>
                    </tr>
                    <tr v-if="!filas.length"><td :colspan="3 + preguntas.length" class="px-3 py-8 text-center text-slate-500">Todavía nadie respondió.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
