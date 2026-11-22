<script setup>
/**
 * Recuadro "¿Qué estoy viendo?" (mejora 3), usable en todo el portal.
 * Recibe una clave del diccionario (database/data/aprende/explicaciones.php,
 * p. ej. "rankings.tabla") o un texto directo ({colegio, universidad} o string).
 * El texto cambia con el nivel (mejora 6). Estático: sin IA.
 */
import { ref, computed, onMounted } from 'vue';
import GdTexto from '@/Components/Gd/GdTexto.vue';
import GdNivel from '@/Components/Gd/GdNivel.vue';
import { glosario, cargarGlosario, porNivel } from '@/lib/educacion';

const props = defineProps({
    clave: { type: String, default: '' },
    texto: { type: [String, Object], default: null },
    titulo: { type: String, default: '¿Qué estoy viendo?' },
    plegado: { type: Boolean, default: false },
    conNivel: { type: Boolean, default: true },
});

const abierto = ref(!props.plegado);
const error = ref(false);
onMounted(() => { cargarGlosario().catch(() => { error.value = true; }); });

const contenido = computed(() => {
    if (props.texto) return porNivel(props.texto);
    return porNivel(glosario.value?.explicaciones?.[props.clave]) || '';
});
</script>

<template>
    <aside class="edu-expl" :class="{ cerrado: !abierto }">
        <div class="edu-expl-h">
            <button type="button" class="edu-expl-btn" :aria-expanded="abierto" @click="abierto = !abierto">
                <span class="edu-expl-ico" aria-hidden="true">?</span>{{ titulo }}
                <span class="edu-expl-flecha" aria-hidden="true">{{ abierto ? '▾' : '▸' }}</span>
            </button>
            <GdNivel v-if="abierto && conNivel" compacto />
        </div>
        <div v-if="abierto" class="edu-expl-c">
            <GdTexto v-if="contenido" :texto="contenido" />
            <p v-else-if="error" class="gd-mini-nota">No se pudo cargar la explicación.</p>
            <p v-else class="gd-mini-nota">Cargando…</p>
            <slot />
        </div>
    </aside>
</template>
