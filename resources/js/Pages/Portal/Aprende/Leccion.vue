<script setup>
/**
 * Una lección de la ruta "Aprende comercio exterior" (mejora 1): texto en dos
 * niveles (mejora 6) con términos del glosario (mejora 3) y, al lado, el
 * gráfico real de Bolivia que arma DatosEducativos con las vistas del portal.
 */
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import GdTexto from '@/Components/Gd/GdTexto.vue';
import GdNivel from '@/Components/Gd/GdNivel.vue';
import GdGraficoEdu from '@/Components/Gd/GdGraficoEdu.vue';
import GdExplicacion from '@/Components/Gd/GdExplicacion.vue';
import { nivel, porNivel, postJson } from '@/lib/educacion';
import { getJson, etiquetaGestion } from '@/lib/gd';

const props = defineProps({
    leccion: { type: Object, required: true },
    grafico: { type: Object, default: null },
    gestiones: { type: Array, default: () => [] },
    cobertura: { type: Object, default: null },
    participante: { type: Object, default: null },
    anterior: { type: Object, default: null },
    siguiente: { type: Object, default: null },
    numero: { type: Number, default: 1 },
    total: { type: Number, default: 1 },
});

const g = ref(props.grafico);
const gestion = ref(props.grafico?.gestion ?? null);
const cargando = ref(false);
async function cambiarGestion() {
    cargando.value = true;
    try {
        g.value = await getJson(`/portal/v2/aprende/grafico/${props.leccion.grafico}`, { gestion: gestion.value });
    } finally {
        cargando.value = false;
    }
}
const hayImp = (a) => props.cobertura?.imp?.anios?.includes(a);

const parrafos = computed(() => porNivel(props.leccion.parrafos) || []);
const pregunta = computed(() => porNivel(props.leccion.pregunta));
const completada = ref((props.participante?.lecciones ?? []).includes(props.leccion.slug));
const nuevas = ref([]);
async function completar() {
    const r = await postJson(`/aprende/${props.leccion.slug}/completar`, { nivel: nivel.value });
    completada.value = true;
    nuevas.value = r.insignias_nuevas ?? [];
}
const reflexion = ref('');
</script>

<template>
    <Head :title="`${leccion.titulo} · Aprende`" />
    <div class="page-wrap gd-pt">
        <div class="gd-cab">
            <div>
                <span class="gd-kicker">● Aprende comercio exterior · Lección {{ numero }} de {{ total }} · {{ leccion.minutos }} min</span>
                <h2 class="gd-titulo">{{ leccion.titulo }}</h2>
                <p class="gd-sub">{{ leccion.resumen }}</p>
            </div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <GdNivel />
                <Link class="tool-btn" href="/aprende">← Panel Educación</Link>
            </div>
        </div>

        <div class="edu-leccion">
            <div class="edu-texto">
                <GdTexto v-for="(p, i) in parrafos" :key="nivel + i" :texto="p" />
                <div class="edu-pregunta">
                    <b>Pregunta para pensar</b>
                    <GdTexto tag="span" :texto="pregunta" />
                    <textarea v-model="reflexion" rows="3" placeholder="Escribe tu idea aquí (solo queda en tu pantalla)…"
                              style="width:100%;margin-top:8px;border:1px solid #fde68a;padding:6px 8px;background:#fff;font-size:0.86rem"></textarea>
                </div>
                <div v-if="leccion.explorar?.length" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
                    <Link v-for="e in leccion.explorar" :key="e.ruta" :href="e.ruta" class="tool-btn">{{ e.texto }} →</Link>
                </div>
                <button class="edu-btn" :disabled="completada" @click="completar">{{ completada ? '✓ Lección completada' : 'Marcar como completada' }}</button>
                <p v-for="i in nuevas" :key="i.codigo" class="edu-resultado ok" style="margin-top:10px"><b>{{ i.icono }} ¡Nueva insignia: {{ i.titulo }}!</b>{{ i.descripcion }}</p>
            </div>

            <div class="edu-panel" :class="{ 'gd-cargando': cargando }">
                <div class="bento-h" style="margin-bottom:10px">
                    <h3 style="font-size:0.95rem">Datos reales de Bolivia</h3>
                    <select v-if="leccion.gestion_param && gestiones.length" v-model.number="gestion" class="inv-select" aria-label="Gestión" @change="cambiarGestion">
                        <option v-for="a in gestiones" :key="a" :value="a">Gestión {{ etiquetaGestion(a, cobertura) }}{{ hayImp(a) ? ' · exp. e imp.' : ' (solo exp.)' }}</option>
                    </select>
                </div>
                <GdGraficoEdu v-for="(b, i) in g?.bloques ?? []" :key="i" :bloque="b" />
                <GdExplicacion plegado :con-nivel="false" :texto="{
                    colegio: 'Las cifras de este recuadro se calculan en este momento desde la base del INE cargada en Geodata. Pasa el mouse sobre una barra para ver su valor. Un recuadro punteado o “Sin datos” significa que ese año aún no se cargó.',
                    universidad: 'Fuente: vistas resumen_* y operacion_comercio_exterior (INE), mismas consultas que el portal (PortalVistas). Exportación FOB, importación CIF frontera; años sin un flujo se muestran como “sin datos”, no como cero.',
                }" />
            </div>
        </div>

        <div class="edu-nav">
            <Link v-if="anterior" class="edu-btn sec" :href="`/aprende/${anterior.slug}`">← {{ anterior.titulo }}</Link><span v-else></span>
            <Link v-if="siguiente" class="edu-btn" :href="`/aprende/${siguiente.slug}`">{{ siguiente.titulo }} →</Link>
            <Link v-else class="edu-btn" href="/aprende/retos">Ahora, a los retos →</Link>
        </div>
    </div>
</template>
