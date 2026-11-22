<script setup>
/** Glosario completo (mejora 3) en dos niveles (mejora 6). Contenido estático, sin IA. */
import { ref, computed, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import GdNivel from '@/Components/Gd/GdNivel.vue';
import GdTexto from '@/Components/Gd/GdTexto.vue';
import { nivel, porNivel } from '@/lib/educacion';

const props = defineProps({ terminos: { type: Array, default: () => [] }, cobertura: { type: Object, default: null } });
const q = ref('');
const lista = computed(() => {
    const s = q.value.trim().toLowerCase();
    return props.terminos.filter((t) => !s || `${t.termino} ${t.corta} ${t.colegio} ${t.universidad}`.toLowerCase().includes(s));
});
const resaltado = ref('');
onMounted(() => {
    resaltado.value = window.location.hash.slice(1);
    if (resaltado.value) setTimeout(() => document.getElementById(resaltado.value)?.scrollIntoView({ block: 'center' }), 50);
});
</script>

<template>
    <Head title="Glosario · Aprende" />
    <div class="page-wrap gd-pt">
        <div class="gd-cab">
            <div>
                <span class="gd-kicker">● Panel Educación · Glosario</span>
                <h2 class="gd-titulo">Glosario del comercio exterior</h2>
                <p class="gd-sub">Definiciones en lenguaje llano, con ejemplos calculados con los datos reales de Bolivia. Estos mismos términos aparecen subrayados en el portal: haz clic en ellos para ver su definición.</p>
            </div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <GdNivel />
                <input v-model="q" type="search" class="inv-search" placeholder="Buscar término…" />
            </div>
        </div>
        <div class="grid-2" style="align-items:start">
            <div v-for="t in lista" :id="t.clave" :key="t.clave" class="bento-box" :style="resaltado === t.clave ? 'outline:2px solid var(--wine)' : ''">
                <div class="bento-h"><h3>{{ t.termino }}</h3><span class="badge-var">{{ nivel === 'colegio' ? 'Colegio' : 'Universidad' }}</span></div>
                <p style="font-size:0.8rem;color:var(--muted);margin-bottom:6px">{{ t.corta }}</p>
                <GdTexto :texto="nivel === 'universidad' ? t.universidad : t.colegio" style="font-size:0.92rem" />
                <p v-if="t.ejemplo" class="edu-pop-e" style="margin-top:8px;font-size:0.84rem"><i>Ejemplo con datos reales:</i> {{ porNivel(t.ejemplo) }}</p>
                <Link v-if="t.ver" :href="t.ver" style="display:inline-block;margin-top:8px;font-weight:800;font-size:0.8rem;color:var(--wine)">Verlo en el portal →</Link>
            </div>
        </div>
        <p v-if="!lista.length" class="gd-nota">No hay términos que coincidan con “{{ q }}”.</p>
    </div>
</template>
