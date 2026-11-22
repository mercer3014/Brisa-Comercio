<script setup>
/** Una historia paso a paso (mejora 5), con texto por nivel (mejora 6) y glosario (mejora 3). */
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import GdNivel from '@/Components/Gd/GdNivel.vue';
import GdTexto from '@/Components/Gd/GdTexto.vue';
import GdGraficoEdu from '@/Components/Gd/GdGraficoEdu.vue';
import GdSinDatos from '@/Components/Gd/GdSinDatos.vue';
import { nivel, porNivel } from '@/lib/educacion';

const props = defineProps({
    historia: { type: Object, required: true },
    cobertura: { type: Object, default: null },
    participante: { type: Object, default: null },
});
const i = ref(0);
const vistos = ref(new Set([0]));
const paso = computed(() => props.historia.pasos[i.value] ?? null);
const total = computed(() => props.historia.pasos.length);
function ir(n) {
    if (n < 0 || n >= total.value) return;
    i.value = n;
    vistos.value = new Set([...vistos.value, n]);
}
function tecla(e) {
    if (e.target?.closest?.('input,textarea,select')) return;
    if (e.key === 'ArrowRight') ir(i.value + 1);
    if (e.key === 'ArrowLeft') ir(i.value - 1);
}
onMounted(() => window.addEventListener('keydown', tecla));
onBeforeUnmount(() => window.removeEventListener('keydown', tecla));
</script>

<template>
    <Head :title="`${historia.titulo} · Historias`" />
    <div class="page-wrap gd-pt">
        <div class="gd-cab">
            <div>
                <span class="gd-kicker">● Historia con datos · paso {{ i + 1 }} de {{ total }}</span>
                <h2 class="gd-titulo">{{ historia.titulo }}</h2>
                <p class="gd-sub">{{ historia.resumen }}</p>
            </div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <GdNivel />
                <Link class="tool-btn" href="/historias">← Todas las historias</Link>
            </div>
        </div>

        <GdSinDatos v-if="!historia.disponible" titulo="Esta historia todavía no tiene datos"
                    mensaje="Su fuente aún no está cargada en la base de Geodata. Aparecerá automáticamente cuando se cargue." />

        <div class="edu-progreso"><i :style="`width:${((i + 1) / total) * 100}%`"></i></div>
        <div class="edu-historia">
            <nav class="edu-pasos" aria-label="Pasos">
                <button v-for="(p, n) in historia.pasos" :key="n" type="button" class="edu-paso" :class="{ on: n === i, visto: vistos.has(n) }" @click="ir(n)">
                    {{ n + 1 }}. {{ p.titulo }}
                </button>
            </nav>
            <div v-if="paso" class="edu-leccion" style="margin:0">
                <div class="edu-texto">
                    <h3 style="font-size:1.15rem;font-weight:800;color:var(--navy);margin-bottom:10px">{{ paso.titulo }}</h3>
                    <GdTexto :key="nivel + i" :texto="porNivel(paso.texto)" />
                    <Link v-if="paso.enlace" :href="paso.enlace.ruta" class="tool-btn" style="display:inline-block;margin-top:6px">{{ paso.enlace.texto }} →</Link>
                    <div class="edu-nav" style="margin-top:20px">
                        <button class="edu-btn sec" :disabled="i === 0" @click="ir(i - 1)">← Anterior</button>
                        <button v-if="i < total - 1" class="edu-btn" @click="ir(i + 1)">Siguiente →</button>
                        <Link v-else class="edu-btn" href="/aprende/retos">Pon a prueba lo aprendido →</Link>
                    </div>
                    <p class="gd-mini-nota" style="margin-top:8px">Tip: usa las flechas ← → del teclado.</p>
                </div>
                <div class="edu-panel">
                    <GdGraficoEdu v-if="paso.grafico?.tipo" :bloque="paso.grafico" />
                </div>
            </div>
        </div>
    </div>
</template>
