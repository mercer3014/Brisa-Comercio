<script setup>
/**
 * Modo docente (mejora 4): crear actividad. Las preguntas "dato" usan los
 * mismos tipos de consulta que los retos (EvaluadorReto::TIPOS): el sistema
 * calcula la respuesta en vivo, el docente no escribe ninguna cifra.
 */
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    tipos: { type: Object, default: () => ({}) },
    paginas: { type: Object, default: () => ({}) },
    gestiones: { type: Array, default: () => [] },
    cobertura: { type: Object, default: null },
    retos: { type: Array, default: () => [] },
});

const nuevaPregunta = (tipo = 'dato') => ({
    enunciado: '', tipo,
    consulta: { tipo: 'top_pais', gestion: props.gestiones[0] ?? null, desde: null, hasta: null, flujo: 'exp', tolerancia: 1 },
    opciones: ['', '', ''], correcta: 0,
});

const form = useForm({
    titulo: '', consigna: '', nivel: 'colegio', cierra_en: '',
    enlace: { pagina: '/rankings', gestion: props.gestiones[0] ?? null, anios: '', flujo: 'exp', dimension: 'pais' },
    preguntas: [nuevaPregunta()],
});

const usa = (p, param) => (props.tipos[p.consulta.tipo]?.params ?? []).includes(param);
const anios = computed(() => [...props.gestiones].sort((a, b) => a - b));
const hayImp = (a) => props.cobertura?.imp?.anios?.includes(a);
const vistaEnlace = computed(() => {
    const e = form.enlace;
    if (!e.pagina) return '';
    const q = new URLSearchParams();
    if (e.gestion) q.set('gestion', e.gestion);
    if (e.anios) q.set('anios', e.anios.replace(/[^0-9,]/g, ''));
    if (e.flujo) q.set('flujo', e.flujo);
    if (e.dimension && e.pagina === '/rankings') q.set('dimension', e.dimension);
    const s = q.toString();
    return e.pagina + (s ? `?${s}` : '');
});

function agregar(tipo) { form.preguntas.push(nuevaPregunta(tipo)); }
function quitar(i) { form.preguntas.splice(i, 1); }
function desdeReto(i, slug) {
    const r = props.retos.find((x) => x.slug === slug);
    if (!r) return;
    const p = form.preguntas[i];
    p.tipo = 'dato';
    p.enunciado = r.enunciado;
    p.consulta = { tipo: r.consulta.tipo, gestion: r.consulta.gestion ?? null, desde: r.consulta.desde ?? null, hasta: r.consulta.hasta ?? null, flujo: r.consulta.flujo ?? 'exp', tolerancia: r.consulta.tolerancia ?? 1 };
}
function guardar() {
    form.transform((d) => ({
        ...d,
        cierra_en: d.cierra_en || null,
        enlace: { ...d.enlace, dimension: d.enlace.pagina === '/rankings' ? d.enlace.dimension : null, anios: d.enlace.anios || null },
        preguntas: d.preguntas.map((p) => {
            const c = {};
            for (const k of ['gestion', 'desde', 'hasta', 'flujo', 'tolerancia']) if (usa(p, k) && p.consulta[k] !== null && p.consulta[k] !== '') c[k] = p.consulta[k];
            if (usa(p, 'gestion') && p.consulta.tipo !== 'tasa_cobertura' && p.consulta.tipo !== 'signo_saldo') c.flujo = p.consulta.flujo;
            return { enunciado: p.enunciado, tipo: p.tipo, consulta: p.tipo === 'dato' ? { tipo: p.consulta.tipo, ...c } : null,
                opciones: p.tipo === 'opcion' ? p.opciones : null, correcta: p.tipo === 'opcion' ? p.correcta : null };
        }),
    })).post('/admin/actividades');
}
const inp = 'w-full rounded border border-slate-300 px-2 py-1.5 text-sm';
const lbl = 'block text-xs font-medium text-slate-600 mb-1';
</script>

<template>
    <Head title="Nueva actividad" />
    <div class="max-w-4xl mx-auto">
        <Link href="/admin/actividades" class="text-sm text-slate-500 hover:underline">← Actividades</Link>
        <h1 class="text-2xl font-bold text-slate-800 mt-1 mb-1">Nueva actividad</h1>
        <p class="text-slate-500 text-sm mb-5">Las preguntas “con datos” se corrigen solas con las cifras del INE cargadas: exportaciones {{ cobertura?.exp?.etiqueta }}, importaciones {{ cobertura?.imp?.etiqueta }}.</p>

        <form class="space-y-4" @submit.prevent="guardar">
            <div class="bg-white rounded-xl border border-slate-200 p-4 grid md:grid-cols-3 gap-3">
                <div class="md:col-span-2"><label :class="lbl">Título</label><input v-model="form.titulo" :class="inp" maxlength="160" placeholder="Ej.: ¿Con quién comercia Bolivia?" />
                    <p v-if="form.errors.titulo" class="text-xs text-red-600 mt-1">{{ form.errors.titulo }}</p></div>
                <div><label :class="lbl">Nivel</label>
                    <select v-model="form.nivel" :class="inp"><option value="colegio">Colegio (tolerancia doble)</option><option value="universidad">Universidad</option></select></div>
                <div class="md:col-span-3"><label :class="lbl">Consigna para los estudiantes</label>
                    <textarea v-model="form.consigna" rows="4" :class="inp" maxlength="4000" placeholder="Ej.: Abran el ranking de países de 2010 y respondan las preguntas…"></textarea>
                    <p v-if="form.errors.consigna" class="text-xs text-red-600 mt-1">{{ form.errors.consigna }}</p></div>
                <div><label :class="lbl">Cierra (opcional)</label><input v-model="form.cierra_en" type="datetime-local" :class="inp" /></div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <h2 class="font-semibold text-slate-800 mb-3">Enlace con filtros prearmados</h2>
                <div class="grid md:grid-cols-5 gap-3">
                    <div class="md:col-span-2"><label :class="lbl">Página del portal</label>
                        <select v-model="form.enlace.pagina" :class="inp"><option value="">Sin enlace</option><option v-for="(t, r) in paginas" :key="r" :value="r">{{ t }}</option></select></div>
                    <div><label :class="lbl">Gestión</label>
                        <select v-model.number="form.enlace.gestion" :class="inp"><option :value="null">—</option><option v-for="a in gestiones" :key="a" :value="a">{{ a }}</option></select></div>
                    <div><label :class="lbl">Flujo</label>
                        <select v-model="form.enlace.flujo" :class="inp"><option value="">—</option><option value="exp">Exportación</option><option value="imp">Importación</option></select></div>
                    <div v-if="form.enlace.pagina === '/rankings'"><label :class="lbl">Dimensión</label>
                        <select v-model="form.enlace.dimension" :class="inp"><option value="pais">País</option><option value="producto">Producto</option><option value="departamento">Departamento</option><option value="seccion">Sección</option></select></div>
                    <div v-if="['/rankings', '/indicadores'].includes(form.enlace.pagina)" class="md:col-span-2"><label :class="lbl">Comparar años (opcional, ej. 2010,2015)</label><input v-model="form.enlace.anios" :class="inp" /></div>
                </div>
                <p v-if="vistaEnlace" class="text-xs text-slate-500 mt-2">Los estudiantes abrirán: <span class="font-mono">{{ vistaEnlace }}</span></p>
            </div>

            <div v-for="(p, i) in form.preguntas" :key="i" class="bg-white rounded-xl border border-slate-200 p-4 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-semibold text-slate-800">Pregunta {{ i + 1 }}</h2>
                    <div class="flex gap-2 items-center">
                        <select v-model="p.tipo" class="rounded border border-slate-300 px-2 py-1 text-sm">
                            <option value="dato">Con datos (corrección automática)</option><option value="opcion">Opción múltiple</option><option value="abierta">Abierta</option>
                        </select>
                        <select class="rounded border border-slate-300 px-2 py-1 text-sm" @change="desdeReto(i, $event.target.value); $event.target.value = ''">
                            <option value="">Copiar de un reto…</option><option v-for="r in retos" :key="r.slug" :value="r.slug">{{ r.titulo }}</option>
                        </select>
                        <button v-if="form.preguntas.length > 1" type="button" class="text-sm text-red-600" @click="quitar(i)">Quitar</button>
                    </div>
                </div>
                <div><label :class="lbl">Enunciado</label><textarea v-model="p.enunciado" rows="2" :class="inp" maxlength="1000"></textarea>
                    <p v-if="form.errors[`preguntas.${i}.enunciado`]" class="text-xs text-red-600 mt-1">{{ form.errors[`preguntas.${i}.enunciado`] }}</p></div>

                <div v-if="p.tipo === 'dato'" class="grid md:grid-cols-4 gap-3 bg-slate-50 rounded p-3">
                    <div class="md:col-span-2"><label :class="lbl">Qué se calcula</label>
                        <select v-model="p.consulta.tipo" :class="inp"><option v-for="(t, k) in tipos" :key="k" :value="k">{{ t.etiqueta }}</option></select></div>
                    <div v-if="usa(p, 'gestion')"><label :class="lbl">Gestión</label>
                        <select v-model.number="p.consulta.gestion" :class="inp"><option v-for="a in gestiones" :key="a" :value="a">{{ a }}{{ hayImp(a) ? '' : ' (solo exp.)' }}</option></select></div>
                    <div v-if="usa(p, 'flujo')"><label :class="lbl">Flujo</label>
                        <select v-model="p.consulta.flujo" :class="inp"><option value="exp">Exportación</option><option value="imp">Importación</option></select></div>
                    <div v-if="usa(p, 'desde')"><label :class="lbl">Desde</label>
                        <select v-model.number="p.consulta.desde" :class="inp"><option v-for="a in anios" :key="a" :value="a">{{ a }}</option></select></div>
                    <div v-if="usa(p, 'hasta')"><label :class="lbl">Hasta</label>
                        <select v-model.number="p.consulta.hasta" :class="inp"><option v-for="a in anios" :key="a" :value="a">{{ a }}</option></select></div>
                    <div v-if="usa(p, 'tolerancia')"><label :class="lbl">Tolerancia (puntos %)</label><input v-model.number="p.consulta.tolerancia" type="number" step="0.5" min="0" max="50" :class="inp" /></div>
                    <p class="md:col-span-4 text-xs text-slate-500">La respuesta correcta no se escribe: se calcula al corregir. Si la gestión o el flujo no están cargados, la pregunta aparece como “no disponible”.</p>
                </div>

                <div v-else-if="p.tipo === 'opcion'" class="space-y-2 bg-slate-50 rounded p-3">
                    <div v-for="(o, j) in p.opciones" :key="j" class="flex gap-2 items-center">
                        <input v-model.number="p.correcta" type="radio" :value="j" :name="`correcta-${i}`" title="Marcar como correcta" />
                        <input v-model="p.opciones[j]" :class="inp" :placeholder="`Opción ${j + 1}`" maxlength="200" />
                    </div>
                    <button v-if="p.opciones.length < 8" type="button" class="text-sm text-marca-700" @click="p.opciones.push('')">+ Opción</button>
                    <p class="text-xs text-slate-500">Marca con el círculo la opción correcta.</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" class="px-3 py-1.5 rounded bg-slate-200 text-sm" @click="agregar('dato')">+ Pregunta con datos</button>
                <button type="button" class="px-3 py-1.5 rounded bg-slate-200 text-sm" @click="agregar('opcion')">+ Opción múltiple</button>
                <button type="button" class="px-3 py-1.5 rounded bg-slate-200 text-sm" @click="agregar('abierta')">+ Abierta</button>
            </div>
            <p v-if="Object.keys(form.errors).length" class="text-sm text-red-600">Revisa los campos marcados.</p>
            <div class="flex gap-2">
                <button class="px-4 py-2 rounded bg-marca-700 text-white text-sm font-medium" :disabled="form.processing">{{ form.processing ? 'Guardando…' : 'Crear actividad y generar código' }}</button>
                <Link href="/admin/actividades" class="px-4 py-2 rounded bg-slate-200 text-sm">Cancelar</Link>
            </div>
        </form>
    </div>
</template>
