<script setup>
/** Lista de retos (mejora 2), puntos, insignias y tabla de apodos. */
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import GdNivel from '@/Components/Gd/GdNivel.vue';
import GdExplicacion from '@/Components/Gd/GdExplicacion.vue';

const props = defineProps({
    retos: { type: Array, default: () => [] },
    insignias: { type: Array, default: () => [] },
    ranking: { type: Array, default: () => [] },
    participante: { type: Object, default: null },
    cobertura: { type: Object, default: null },
});
const resueltos = computed(() => new Set(props.participante?.retos ?? []));
const mias = computed(() => new Set((props.participante?.insignias ?? []).map((i) => i.codigo)));
const TIPO = { opcion: 'Elegir', numero: 'Calcular', binaria: 'Sí / No' };
</script>

<template>
    <Head title="Retos · Aprende" />
    <div class="page-wrap gd-pt">
        <div class="gd-cab">
            <div>
                <span class="gd-kicker">● Panel Educación · Retos y preguntas guiadas</span>
                <h2 class="gd-titulo">Retos con datos reales</h2>
                <p class="gd-sub">Usa el portal (rankings, indicadores, línea de tiempo) para encontrar la respuesta. La comprobación se hace en vivo contra la base: si se cargan más años, los retos siguen siendo válidos.</p>
            </div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <GdNivel />
                <Link class="tool-btn" href="/aprende">← Panel Educación</Link>
            </div>
        </div>

        <GdExplicacion :texto="{
            colegio: 'Cada reto te dice a qué página ir y qué filtros poner. Con el botón “Abrir ya filtrado” llegas directo. Ganas puntos solo la primera vez que aciertas; si fallas dos veces, te mostramos la respuesta y su cálculo.',
            universidad: 'La respuesta correcta no está guardada: un evaluador ejecuta la misma consulta que el ranking o el indicador (país por ISO, número con tolerancia, superávit/déficit). En nivel Universidad la tolerancia numérica es la mitad que en Colegio.',
        }" titulo="¿Cómo funcionan los retos?" />

        <div class="grid-2" style="align-items:start;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr)">
            <div class="edu-lista">
                <Link v-for="(r, i) in retos" :key="r.slug" :href="`/aprende/retos/${r.slug}`" class="edu-item" :class="{ hecho: resueltos.has(r.slug) }">
                    <span class="num">{{ resueltos.has(r.slug) ? '✓' : i + 1 }}</span>
                    <span><b>{{ r.titulo }}</b><small>{{ r.enunciado }}</small></span>
                    <span style="display:flex;flex-direction:column;gap:4px;align-items:flex-end">
                        <span class="edu-badge" :class="{ ok: resueltos.has(r.slug) }">{{ r.puntos }} pts</span>
                        <span class="edu-badge" :class="{ uni: r.nivel === 'universidad' }">{{ r.nivel === 'ambos' ? TIPO[r.tipo_respuesta] : 'Universidad' }}</span>
                    </span>
                </Link>
            </div>
            <div style="display:flex;flex-direction:column;gap:16px">
                <div class="bento-box">
                    <div class="bento-h"><h3>Tu marcador</h3></div>
                    <p v-if="participante" style="font-size:0.9rem"><b>{{ participante.apodo }}</b> · {{ participante.puntos }} puntos · {{ participante.retos.length }}/{{ retos.length }} retos</p>
                    <p v-else style="font-size:0.86rem;color:var(--muted)">Responde tu primer reto y te asignamos un apodo (puedes cambiarlo en <Link href="/aprende" style="color:var(--wine);font-weight:800">el panel</Link>).</p>
                    <div class="edu-insignias" style="margin-top:10px">
                        <div v-for="i in insignias" :key="i.codigo" class="edu-insignia" :class="{ ok: mias.has(i.codigo) }" :title="i.descripcion">
                            <span class="ico">{{ i.icono }}</span><b>{{ i.titulo }}</b><small>{{ i.descripcion }}</small>
                        </div>
                    </div>
                </div>
                <div class="bento-box">
                    <div class="bento-h"><h3>Top 10 de apodos</h3></div>
                    <ol v-if="ranking.length" style="font-size:0.88rem;padding-left:18px;list-style:decimal">
                        <li v-for="r in ranking" :key="r.apodo" style="padding:3px 0"><b>{{ r.apodo }}</b> — {{ r.puntos }} pts</li>
                    </ol>
                    <p v-else style="font-size:0.84rem;color:var(--muted)">Todavía nadie sumó puntos. ¡Sé el primero!</p>
                </div>
            </div>
        </div>
    </div>
</template>
