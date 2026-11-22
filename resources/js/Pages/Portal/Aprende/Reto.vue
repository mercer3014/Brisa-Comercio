<script setup>
/**
 * Un reto (mejora 2). Dice DÓNDE ir y QUÉ filtros usar, con un enlace profundo
 * ya filtrado. La respuesta se comprueba en el servidor (EvaluadorReto) con la
 * misma consulta del portal: aquí no viaja la respuesta correcta.
 * Nivel (mejora 6): en Colegio la pista se ve siempre y la tolerancia es mayor.
 */
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import GdNivel from '@/Components/Gd/GdNivel.vue';
import GdSinDatos from '@/Components/Gd/GdSinDatos.vue';
import { nivel, porNivel, postJson } from '@/lib/educacion';

const props = defineProps({
    reto: { type: Object, required: true },
    estado: { type: Object, required: true },
    intentos: { type: Object, default: () => ({ total: 0, resuelto: false }) },
    siguiente: { type: String, default: null },
    numero: { type: Number, default: null },
    total: { type: Number, default: null },
    participante: { type: Object, default: null },
    cobertura: { type: Object, default: null },
});

const respuesta = ref('');
const filtro = ref('');
const verPista = ref(false);
const enviando = ref(false);
const resultado = ref(null);
const error = ref('');
const resuelto = ref(props.intentos.resuelto);
const puntos = ref(props.participante?.puntos ?? 0);

const pistaVisible = computed(() => nivel.value === 'colegio' || verPista.value);
const opcionesFiltradas = computed(() => {
    const q = filtro.value.trim().toLowerCase();
    return (props.estado.opciones ?? []).filter((o) => !q || o.etiqueta.toLowerCase().includes(q));
});

async function enviar() {
    if (!String(respuesta.value).trim()) return;
    enviando.value = true;
    error.value = '';
    try {
        const r = await postJson(`/aprende/retos/${props.reto.slug}/responder`, { respuesta: String(respuesta.value), nivel: nivel.value });
        resultado.value = r;
        if (r.correcta) resuelto.value = true;
        puntos.value = r.participante?.puntos ?? puntos.value;
    } catch (e) {
        error.value = e?.response?.status === 429 ? 'Demasiados intentos seguidos. Espera un minuto.' : (e?.response?.data?.mensaje || 'No se pudo comprobar la respuesta.');
    } finally {
        enviando.value = false;
    }
}
</script>

<template>
    <Head :title="`${reto.titulo} · Retos`" />
    <div class="page-wrap gd-pt">
        <div class="gd-cab">
            <div>
                <span class="gd-kicker">● Reto {{ numero }} de {{ total }} · {{ reto.puntos }} puntos<template v-if="reto.nivel === 'universidad'"> · nivel universidad</template></span>
                <h2 class="gd-titulo">{{ reto.titulo }}</h2>
            </div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <GdNivel />
                <Link class="tool-btn" href="/aprende/retos">← Todos los retos</Link>
            </div>
        </div>

        <div class="edu-reto">
            <div class="bento-box">
                <p class="edu-enunciado">{{ reto.enunciado }}</p>

                <template v-if="estado.disponible">
                    <div v-if="pistaVisible" class="edu-pista"><b>Dónde buscar:</b> {{ porNivel(reto.pista) }}</div>
                    <button v-else type="button" class="tool-btn" style="margin-bottom:12px" @click="verPista = true">Ver pista</button>

                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
                        <a v-if="reto.enlace" class="edu-btn navy" :href="`/aprende/retos/${reto.slug}/abrir`" target="_blank" rel="noopener">Abrir ya filtrado ↗</a>
                        <code v-if="reto.enlace" style="font-size:0.76rem;color:var(--muted);align-self:center">{{ reto.enlace }}</code>
                    </div>

                    <form class="edu-form" @submit.prevent="enviar">
                        <template v-if="estado.tipo === 'opcion'">
                            <input v-if="(estado.opciones?.length ?? 0) > 12" v-model="filtro" type="search" placeholder="Buscar en la lista…" />
                            <select v-model="respuesta" :size="Math.min(8, Math.max(4, opcionesFiltradas.length))" aria-label="Respuesta">
                                <option v-for="o in opcionesFiltradas" :key="o.valor" :value="o.valor">{{ o.etiqueta }}</option>
                            </select>
                        </template>
                        <template v-else-if="estado.tipo === 'binaria'">
                            <label v-for="o in estado.opciones" :key="o.valor" class="edu-opcion" :class="{ on: respuesta === o.valor }">
                                <input v-model="respuesta" type="radio" :value="o.valor" /> {{ o.etiqueta }}
                            </label>
                        </template>
                        <template v-else>
                            <div style="display:flex;gap:8px;align-items:center">
                                <input v-model="respuesta" type="text" inputmode="decimal" placeholder="Ej.: -12,5" style="max-width:200px" />
                                <b>{{ estado.unidad }}</b>
                            </div>
                            <small class="gd-mini-nota">Usa coma o punto para los decimales.</small>
                        </template>
                        <div><button class="edu-btn" :disabled="enviando || !String(respuesta).trim()">{{ enviando ? 'Comprobando…' : 'Comprobar respuesta' }}</button></div>
                    </form>

                    <p v-if="error" class="edu-resultado mal">{{ error }}</p>
                    <div v-if="resultado" class="edu-resultado" :class="resultado.correcta ? 'ok' : 'mal'">
                        <b>{{ resultado.correcta ? '✓ ' : '✗ ' }}{{ resultado.mensaje }}<template v-if="resultado.puntos"> +{{ resultado.puntos }} puntos</template></b>
                        <template v-if="resultado.revelada">
                            <div>Respuesta: <b style="display:inline">{{ resultado.esperada }}</b></div>
                            <div style="margin-top:4px">{{ resultado.explicacion }}</div>
                        </template>
                        <div v-for="i in resultado.insignias_nuevas" :key="i.codigo" style="margin-top:8px">{{ i.icono }} <b style="display:inline">¡Nueva insignia: {{ i.titulo }}!</b> {{ i.descripcion }}</div>
                    </div>
                </template>
                <GdSinDatos v-else titulo="Este reto no está disponible con los datos cargados" :mensaje="estado.motivo" />
            </div>

            <div style="display:flex;flex-direction:column;gap:14px">
                <div class="bento-box">
                    <div class="bento-h"><h3>Estado</h3></div>
                    <p style="font-size:0.9rem">{{ resuelto ? '✓ Reto resuelto' : 'Sin resolver todavía' }}</p>
                    <p style="font-size:0.9rem">Tus puntos: <b>{{ puntos }}</b></p>
                    <p class="gd-mini-nota" style="margin-top:6px">Puntos solo en el primer acierto, y solo si la respuesta no se mostró antes (después de dos intentos fallidos se revela).</p>
                </div>
                <Link v-if="siguiente" class="edu-btn" :href="`/aprende/retos/${siguiente}`">Siguiente reto →</Link>
                <Link v-else class="edu-btn" href="/historias">Ver historias con datos →</Link>
            </div>
        </div>
    </div>
</template>
