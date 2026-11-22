<script setup>
/**
 * Panel Educación (track de educación, NASA Space Apps): portada que reúne las
 * seis mejoras — ruta de lecciones, retos, glosario/"¿Qué estoy viendo?",
 * modo docente (código de actividad), historias y nivel colegio/universidad.
 * Todas las cifras de lecciones, retos e historias salen en vivo de brisa.
 */
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import GdNivel from '@/Components/Gd/GdNivel.vue';
import GdCobertura from '@/Components/Gd/GdCobertura.vue';
import GdExplicacion from '@/Components/Gd/GdExplicacion.vue';
import { nivel, postJson } from '@/lib/educacion';

const props = defineProps({
    pitch: { type: String, default: '' },
    cobertura: { type: Object, default: null },
    participante: { type: Object, default: null },
    lecciones: { type: Array, default: () => [] },
    retos: { type: Array, default: () => [] },
    historias: { type: Array, default: () => [] },
    insignias: { type: Array, default: () => [] },
});

const yo = ref(props.participante);
const apodo = ref(props.participante?.apodo ?? '');
const guardando = ref(false);
const msg = ref('');
async function guardarApodo() {
    guardando.value = true;
    msg.value = '';
    try {
        const r = await postJson('/aprende/participante', { apodo: apodo.value.trim() || null, nivel: nivel.value });
        yo.value = r.participante;
        msg.value = 'Listo, ¡bienvenido/a!';
    } catch (e) {
        msg.value = e?.response?.status === 422 ? 'Usa solo letras, números, espacios, punto o guion (máx. 30).' : e?.response?.status === 429 ? 'Demasiados intentos seguidos. Espera un minuto.' : 'No se pudo guardar. Intenta de nuevo.';
    } finally {
        guardando.value = false;
    }
}

const hechas = computed(() => new Set(yo.value?.lecciones ?? []));
const resueltos = computed(() => new Set(yo.value?.retos ?? []));
const misInsignias = computed(() => new Set((yo.value?.insignias ?? []).map((i) => i.codigo)));
const page = usePage();
const usuario = computed(() => page.props.auth?.usuario ?? null);

const codigo = ref('');
function irActividad() {
    const c = codigo.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
    if (c) router.visit(`/actividad/${c}`);
}
</script>

<template>
    <Head title="Educación · Aprende comercio exterior" />
    <div class="page-wrap gd-pt">
        <section class="edu-hero">
            <div>
                <span class="edu-pill">PANEL EDUCACIÓN · DATOS REALES DEL INE</span>
                <h2>Aprende comercio exterior con los datos de Bolivia</h2>
                <blockquote>“{{ pitch }}”</blockquote>
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                    <Link class="edu-btn" :href="`/aprende/${lecciones[0]?.slug ?? ''}`">Empezar la ruta →</Link>
                    <Link class="edu-btn sec" href="/aprende/retos">Ir a los retos</Link>
                    <GdNivel />
                </div>
            </div>
            <div class="edu-perfil">
                <b style="font-size:0.78rem;letter-spacing:.1em;text-transform:uppercase;color:#e7b9c1">Tu progreso</b>
                <div class="edu-stats">
                    <div><b>{{ yo?.puntos ?? 0 }}</b><small>Puntos</small></div>
                    <div><b>{{ yo?.lecciones?.length ?? 0 }}/{{ lecciones.length }}</b><small>Lecciones</small></div>
                    <div><b>{{ yo?.retos?.length ?? 0 }}/{{ retos.length }}</b><small>Retos</small></div>
                    <div><b>{{ yo?.insignias?.length ?? 0 }}</b><small>Insignias</small></div>
                </div>
                <label style="font-size:0.8rem">Tu apodo (no pedimos nombre real ni correo)</label>
                <div style="display:flex;gap:6px">
                    <input v-model="apodo" maxlength="30" placeholder="Ej.: Equipo Cóndor" @keydown.enter="guardarApodo" />
                    <button class="edu-btn navy" :disabled="guardando" @click="guardarApodo">{{ yo ? 'Cambiar' : 'Empezar' }}</button>
                </div>
                <small v-if="msg" style="color:#e7b9c1">{{ msg }}</small>
                <small style="color:rgba(255,255,255,.7)">El progreso se guarda en este navegador (sesión) y en la base, solo con el apodo.</small>
            </div>
        </section>

        <GdExplicacion :texto="{
            colegio: 'Aquí no hay ejemplos inventados: cada número sale de la base del [[ine|INE]] cargada en Geodata. Elige tu nivel arriba: en Colegio las explicaciones son más simples y las cifras se redondean; en Universidad se usan términos técnicos como [[hhi|HHI]] o [[cif|CIF]].',
            universidad: 'Todas las cifras se calculan en vivo sobre los microdatos del [[ine|INE]] (regla única: exportación [[fob|FOB]], importación [[cif|CIF frontera]]). El nivel Universidad muestra metodología, [[hhi|HHI]], [[nandina|NANDINA]] de 10 dígitos y [[tasa-cobertura|tasa de cobertura]].',
        }" titulo="¿Cómo funciona este panel?" />

        <div class="edu-modulos">
            <Link class="edu-modulo" href="/aprende/exportar-e-importar" style="--c:#5e1224">
                <span class="n">1 · Ruta</span><h3>Aprende comercio exterior</h3>
                <p>{{ lecciones.length }} lecciones cortas, cada una con un gráfico real de Bolivia y una pregunta para pensar.</p><span class="ir">Ver lecciones →</span>
            </Link>
            <Link class="edu-modulo" href="/aprende/retos" style="--c:#3b7a8c">
                <span class="n">2 · Retos</span><h3>Retos y preguntas guiadas</h3>
                <p>{{ retos.length }} retos: usa los rankings y la línea de tiempo para responder. La web te dice si acertaste. Puntos e insignias.</p><span class="ir">Resolver retos →</span>
            </Link>
            <Link class="edu-modulo" href="/aprende/glosario" style="--c:#f59e0b">
                <span class="n">3 · Glosario</span><h3>¿Qué estoy viendo?</h3>
                <p>FOB, CIF, NANDINA, HHI, ISO… en lenguaje llano. Los términos subrayados del portal se abren con un clic.</p><span class="ir">Abrir glosario →</span>
            </Link>
            <Link class="edu-modulo" href="/historias" style="--c:#10b981">
                <span class="n">5 · Historias</span><h3>Historias con datos</h3>
                <p>Recorridos paso a paso: el boom exportador, la soya boliviana, la balanza de los 90.</p><span class="ir">Ver historias →</span>
            </Link>
            <div class="edu-modulo" style="--c:#12203d">
                <span class="n">4 · Modo docente</span><h3>¿Tu profe te dio un código?</h3>
                <div style="display:flex;gap:6px"><input v-model="codigo" class="inv-search" maxlength="10" placeholder="Ej.: K7M2QX" style="min-width:0;flex:1" @keydown.enter="irActividad" />
                    <button class="edu-btn navy" @click="irActividad">Entrar</button></div>
                <p style="margin-top:4px">Docentes: crean actividades en <a :href="usuario ? '/admin/actividades' : '/acceder'" style="color:var(--wine);font-weight:800">{{ usuario ? 'el panel → Actividades' : 'Acceder (rol docente)' }}</a>.</p>
            </div>
        </div>

        <div class="grid-2" style="align-items:start">
            <div class="bento-box">
                <div class="bento-h"><h3>Ruta “Aprende comercio exterior”</h3><span class="badge-var">{{ hechas.size }}/{{ lecciones.length }} completadas</span></div>
                <div class="edu-lista">
                    <Link v-for="(l, i) in lecciones" :key="l.slug" :href="`/aprende/${l.slug}`" class="edu-item" :class="{ hecho: hechas.has(l.slug) }">
                        <span class="num">{{ hechas.has(l.slug) ? '✓' : i + 1 }}</span>
                        <span><b>{{ l.titulo }}</b><small>{{ l.resumen }}</small></span>
                        <span class="edu-badge">{{ l.minutos }} min</span>
                    </Link>
                </div>
            </div>
            <div class="bento-box">
                <div class="bento-h"><h3>Retos</h3><Link href="/aprende/retos" class="badge-var">Ver todos →</Link></div>
                <div class="edu-lista">
                    <Link v-for="(r, i) in retos.slice(0, 5)" :key="r.slug" :href="`/aprende/retos/${r.slug}`" class="edu-item" :class="{ hecho: resueltos.has(r.slug) }">
                        <span class="num">{{ resueltos.has(r.slug) ? '✓' : i + 1 }}</span>
                        <span><b>{{ r.titulo }}</b><small>{{ r.enunciado }}</small></span>
                        <span class="edu-badge" :class="{ uni: r.nivel === 'universidad' }">{{ r.puntos }} pts</span>
                    </Link>
                </div>
                <h4 style="font-size:0.8rem;font-weight:800;color:var(--muted);text-transform:uppercase;margin:16px 0 8px">Insignias</h4>
                <div class="edu-insignias">
                    <div v-for="i in insignias" :key="i.codigo" class="edu-insignia" :class="{ ok: misInsignias.has(i.codigo) }" :title="i.descripcion">
                        <span class="ico">{{ i.icono }}</span><b>{{ i.titulo }}</b>
                    </div>
                </div>
            </div>
        </div>

        <div class="overview-panel" style="margin-top:22px">
            <div class="bento-h"><h3>Con qué datos trabajas</h3><GdCobertura :cobertura="cobertura" /></div>
            <p style="font-size:0.86rem;color:var(--muted)">Las lecciones, retos e historias usan solo lo que está cargado. Hoy las importaciones del INE están cargadas para
                <b>{{ cobertura?.imp?.etiqueta }}</b>: por eso la balanza comercial y los retos de importación usan esos años, y lo demás aparece como “Sin datos” (nunca como cero).
                Cuando se carguen más años, todo se recalcula solo.</p>
        </div>
    </div>
</template>
