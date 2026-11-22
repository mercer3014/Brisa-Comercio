<script setup>
/**
 * Término del glosario emergente (mejora 3): al pulsarlo abre un recuadro con
 * la definición en el nivel elegido y un ejemplo boliviano calculado en vivo.
 * Contenido estático (database/data/aprende/glosario.php), sin IA.
 */
import { ref, computed, onBeforeUnmount } from 'vue';
import { Link } from '@inertiajs/vue3';
import { glosario, cargarGlosario, nivel, porNivel } from '@/lib/educacion';

const props = defineProps({
    clave: { type: String, required: true },
    texto: { type: String, default: '' },
});

const abierto = ref(false);
const raiz = ref(null);
const termino = computed(() => glosario.value?.terminos?.[props.clave] ?? null);

function cerrarFuera(e) {
    if (raiz.value && !raiz.value.contains(e.target)) cerrar();
}
function tecla(e) {
    if (e.key === 'Escape') cerrar();
}
function cerrar() {
    abierto.value = false;
    document.removeEventListener('click', cerrarFuera, true);
    document.removeEventListener('keydown', tecla);
}
async function alternar() {
    if (abierto.value) return cerrar();
    abierto.value = true;
    document.addEventListener('click', cerrarFuera, true);
    document.addEventListener('keydown', tecla);
    try { await cargarGlosario(); } catch { /* se muestra el aviso */ }
}
onBeforeUnmount(cerrar);
</script>

<template>
    <span ref="raiz" class="edu-term-wrap">
        <button type="button" class="edu-term" :aria-expanded="abierto" @click.stop="alternar">{{ texto || termino?.termino || clave }}</button>
        <span v-if="abierto" class="edu-pop" role="dialog">
            <template v-if="termino">
                <b class="edu-pop-t">{{ termino.termino }}</b>
                <span class="edu-pop-d">{{ nivel === 'universidad' ? termino.universidad : termino.colegio }}</span>
                <span v-if="termino.ejemplo" class="edu-pop-e"><i>Ejemplo con datos reales:</i> {{ porNivel(termino.ejemplo) }}</span>
                <span class="edu-pop-l">
                    <Link v-if="termino.ver" :href="termino.ver">Verlo en el portal →</Link>
                    <Link :href="`/aprende/glosario#${clave}`">Glosario completo</Link>
                </span>
            </template>
            <span v-else-if="glosario" class="edu-pop-d">Término no encontrado en el glosario.</span>
            <span v-else class="edu-pop-d">Cargando definición…</span>
        </span>
    </span>
</template>
