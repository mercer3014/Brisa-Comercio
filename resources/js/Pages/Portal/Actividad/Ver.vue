<script setup>
/**
 * Modo docente, cara del ESTUDIANTE (mejora 4): abre la actividad con el código
 * que dio el profesor, responde con un alias y ve qué acertó. Las preguntas
 * "dato" se corrigen en vivo con la base del INE.
 */
import { ref, reactive, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import GdSinDatos from '@/Components/Gd/GdSinDatos.vue';
import { nivel, postJson } from '@/lib/educacion';

const props = defineProps({
    codigo: { type: String, default: '' },
    actividad: { type: Object, default: null },
    preguntas: { type: Array, default: () => [] },
    yaRespondio: { type: Boolean, default: false },
    cobertura: { type: Object, default: null },
});
if (props.actividad?.nivel) nivel.value = props.actividad.nivel;

const alias = ref('');
const resp = reactive(Object.fromEntries(props.preguntas.map((p) => [p.pregunta_id, ''])));
const enviando = ref(false);
const error = ref('');
const resultados = ref(null);
const enviado = ref(props.yaRespondio);
const porId = computed(() => Object.fromEntries((resultados.value ?? []).map((r) => [r.pregunta_id, r])));
const aciertos = computed(() => (resultados.value ?? []).filter((r) => r.correcta === true).length);
const corregibles = computed(() => (resultados.value ?? []).filter((r) => r.correcta !== null).length);

async function enviar() {
    error.value = '';
    if (!alias.value.trim()) { error.value = 'Escribe un alias (puede ser tu nombre de pila o un apodo).'; return; }
    enviando.value = true;
    try {
        const r = await postJson(`/actividad/${props.codigo}/responder`, { alias: alias.value.trim(), respuestas: { ...resp } });
        resultados.value = r.resultados;
        enviado.value = true;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (e) {
        error.value = e?.response?.data?.mensaje || (e?.response?.status === 422 ? 'Revisa el alias: solo letras, números, espacio, punto o guion (máx. 40).' : e?.response?.status === 429 ? 'Demasiados envíos seguidos. Espera un minuto.' : 'No se pudo enviar. Intenta de nuevo.');
    } finally {
        enviando.value = false;
    }
}
const nuevoCodigo = ref('');
function buscar() {
    const c = nuevoCodigo.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
    if (c) router.visit(`/actividad/${c}`);
}
</script>

<template>
    <Head :title="actividad ? `${actividad.titulo} · Actividad` : 'Actividad'" />
    <div class="page-wrap gd-pt" style="max-width:900px">
        <div class="gd-cab">
            <div>
                <span class="gd-kicker">● Actividad de clase · código {{ codigo || '—' }}</span>
                <h2 class="gd-titulo">{{ actividad ? actividad.titulo : 'Entrar a una actividad' }}</h2>
                <p v-if="actividad" class="gd-sub">Nivel {{ actividad.nivel === 'universidad' ? 'universidad' : 'colegio' }}<template v-if="actividad.cierra_en"> · cierra el {{ new Date(actividad.cierra_en).toLocaleString('es-BO') }}</template></p>
            </div>
            <Link class="tool-btn" href="/aprende">← Panel Educación</Link>
        </div>

        <template v-if="!actividad">
            <div class="bento-box">
                <p v-if="codigo" style="margin-bottom:12px">No encontramos ninguna actividad con el código <b>{{ codigo }}</b>. Revisa que esté bien escrito.</p>
                <p v-else style="margin-bottom:12px">Escribe el código de 6 letras/números que te dio tu profesor.</p>
                <div style="display:flex;gap:8px;max-width:360px">
                    <input v-model="nuevoCodigo" class="inv-search" maxlength="10" placeholder="Ej.: K7M2QX" style="flex:1;min-width:0" @keydown.enter="buscar" />
                    <button class="edu-btn" @click="buscar">Entrar</button>
                </div>
            </div>
        </template>

        <template v-else>
            <div class="bento-box" style="margin-bottom:16px">
                <div class="bento-h"><h3>Consigna</h3></div>
                <p style="white-space:pre-line">{{ actividad.consigna }}</p>
                <a v-if="actividad.enlace" :href="actividad.enlace" target="_blank" rel="noopener" class="edu-btn navy" style="margin-top:12px;display:inline-block">Abrir los datos ya filtrados ↗</a>
            </div>

            <div v-if="resultados" class="edu-resultado ok" style="margin-bottom:16px">
                <b>¡Respuestas enviadas!</b>
                <template v-if="corregibles">Acertaste {{ aciertos }} de {{ corregibles }} preguntas con corrección automática.</template>
                Las preguntas abiertas las revisa tu profesor.
            </div>
            <div v-else-if="enviado" class="edu-resultado ok" style="margin-bottom:16px"><b>Ya enviaste tus respuestas desde este navegador.</b>Tu profesor puede verlas en su panel.</div>
            <GdSinDatos v-if="!actividad.abierta && !enviado" titulo="Actividad cerrada" mensaje="El profesor cerró esta actividad o ya pasó la fecha límite." />

            <form v-if="actividad.abierta && !enviado || resultados" @submit.prevent="enviar">
                <div v-for="(p, n) in preguntas" :key="p.pregunta_id" class="bento-box" style="margin-bottom:12px">
                    <div class="bento-h"><h3 style="font-size:0.95rem">Pregunta {{ n + 1 }}</h3>
                        <span class="badge-var">{{ p.tipo === 'dato' ? 'Con datos (corrección automática)' : p.tipo === 'opcion' ? 'Opción múltiple' : 'Respuesta abierta' }}</span></div>
                    <p style="margin-bottom:10px">{{ p.enunciado }}</p>
                    <GdSinDatos v-if="!p.disponible" titulo="No disponible con los datos cargados" :mensaje="p.motivo" />
                    <div v-else class="edu-form">
                        <select v-if="p.respuesta === 'opcion' && p.tipo === 'dato'" v-model="resp[p.pregunta_id]" :disabled="!!resultados">
                            <option value="">— Elige —</option>
                            <option v-for="o in p.opciones" :key="o.valor" :value="o.valor">{{ o.etiqueta }}</option>
                        </select>
                        <template v-else-if="p.respuesta === 'opcion' || p.respuesta === 'binaria'">
                            <label v-for="o in p.opciones" :key="o.valor" class="edu-opcion" :class="{ on: resp[p.pregunta_id] === o.valor }">
                                <input v-model="resp[p.pregunta_id]" type="radio" :value="o.valor" :disabled="!!resultados" /> {{ o.etiqueta }}
                            </label>
                        </template>
                        <div v-else-if="p.respuesta === 'numero'" style="display:flex;gap:8px;align-items:center">
                            <input v-model="resp[p.pregunta_id]" inputmode="decimal" style="max-width:200px" :disabled="!!resultados" /> <b>{{ p.unidad }}</b>
                        </div>
                        <textarea v-else v-model="resp[p.pregunta_id]" rows="4" maxlength="2000" :disabled="!!resultados" placeholder="Escribe tu respuesta…"></textarea>
                    </div>
                    <div v-if="porId[p.pregunta_id]" class="edu-resultado" :class="porId[p.pregunta_id].correcta === false ? 'mal' : 'ok'">
                        <b>{{ porId[p.pregunta_id].correcta === null ? 'Enviada (la revisa tu profesor)' : porId[p.pregunta_id].correcta ? '✓ Correcta' : '✗ Incorrecta' }}</b>
                        <template v-if="porId[p.pregunta_id].explicacion">{{ porId[p.pregunta_id].explicacion }}</template>
                    </div>
                </div>
                <div v-if="!resultados" class="bento-box">
                    <label style="font-size:0.85rem;font-weight:700">Tu alias para el profesor</label>
                    <div style="display:flex;gap:8px;margin-top:6px;flex-wrap:wrap">
                        <input v-model="alias" class="inv-search" maxlength="40" placeholder="Ej.: Ana P. – 5to B" style="flex:1;min-width:200px" />
                        <button class="edu-btn" :disabled="enviando">{{ enviando ? 'Enviando…' : 'Enviar respuestas' }}</button>
                    </div>
                    <p class="gd-mini-nota" style="margin-top:6px">Solo se guarda el alias y tus respuestas; no pedimos correo ni datos personales. Se envía una sola vez.</p>
                    <p v-if="error" class="edu-resultado mal" style="margin-top:8px">{{ error }}</p>
                </div>
            </form>
        </template>
    </div>
</template>
