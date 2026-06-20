<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { fmtNum } from '../../lib/format';

const props = defineProps({
    titulo: { type: String, required: true },
    subtitulo: { type: String, default: '' },
    fuente: { type: String, default: 'INE — Bolivia' },
    columnas: { type: Array, default: () => [] },
    filas: { type: Array, default: () => [] },
    cargando: { type: Boolean, default: false },
});

const cuerpo = ref(null);
const verDatos = ref(false);
const ampliado = ref(false);

function alternarAmpliado() {
    ampliado.value = !ampliado.value;
}

function alPresionarTecla(e) {
    if (e.key === 'Escape') ampliado.value = false;
}

onMounted(() => window.addEventListener('keydown', alPresionarTecla));
onUnmounted(() => window.removeEventListener('keydown', alPresionarTecla));

function valorCelda(fila, columna) {
    const value = fila[columna.key];
    return columna.formato ? columna.formato(value, fila) : (typeof value === 'number' ? fmtNum(value) : (value ?? '—'));
}

function descargarPng() {
    const svg = cuerpo.value?.querySelector('svg');
    if (!svg) return;

    const clone = svg.cloneNode(true);
    const width = svg.viewBox?.baseVal?.width || svg.clientWidth || 800;
    const height = svg.viewBox?.baseVal?.height || svg.clientHeight || 420;
    clone.setAttribute('width', width);
    clone.setAttribute('height', height);

    const xml = new XMLSerializer().serializeToString(clone);
    const encoded = 'data:image/svg+xml;base64,' + window.btoa(unescape(encodeURIComponent(xml)));

    const image = new Image();
    image.onload = () => {
        const canvas = document.createElement('canvas');
        const scale = 2;
        canvas.width = width * scale;
        canvas.height = height * scale;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(image, 0, 0, canvas.width, canvas.height);
        const link = document.createElement('a');
        link.download = `${props.titulo.replace(/\s+/g, '_').toLowerCase()}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
    };
    image.src = encoded;
}
</script>

<template>
    <!-- Fondo oscuro cuando la tarjeta está ampliada -->
    <Teleport to="body">
        <div v-if="ampliado" class="fixed inset-0 z-40 bg-institucional-900/60 backdrop-blur-sm" @click="ampliado = false"></div>
    </Teleport>

    <section
        class="tarjeta px-5 py-5 sm:px-6"
        :class="ampliado
            ? 'fixed inset-3 z-50 m-0 flex flex-col overflow-auto shadow-2xl sm:inset-6 lg:inset-10'
            : 'relative'"
    >
        <div class="flex flex-col gap-4 border-b border-gris-100 pb-4 md:flex-row md:items-start md:justify-between">
            <div class="min-w-0">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-institucional-400">Visualización</div>
                <h3 class="mt-2 text-2xl font-bold leading-tight text-institucional-900">{{ titulo }}</h3>
                <p v-if="subtitulo" class="mt-2 max-w-2xl text-sm leading-relaxed text-institucional-500">{{ subtitulo }}</p>
            </div>

            <div class="flex items-center gap-2">
                <button
                    v-if="filas.length"
                    class="btn btn-secundario px-3 py-2 text-xs uppercase tracking-[0.14em]"
                    type="button"
                    @click="verDatos = !verDatos"
                >
                    {{ verDatos ? 'Ocultar datos' : 'Ver datos' }}
                </button>
                <button
                    class="btn btn-secundario px-3 py-2 text-xs uppercase tracking-[0.14em]"
                    type="button"
                    :title="ampliado ? 'Cerrar (Esc)' : 'Ampliar (o doble clic)'"
                    @click="alternarAmpliado"
                >
                    {{ ampliado ? '✕ Cerrar' : '⤢ Ampliar' }}
                </button>
                <button class="btn btn-secundario px-3 py-2 text-xs uppercase tracking-[0.14em]" type="button" @click="descargarPng">
                    PNG
                </button>
            </div>
        </div>

        <div ref="cuerpo" class="pt-5" :class="ampliado ? 'flex-1 [&_.apexcharts-canvas]:!mx-auto' : ''" @dblclick="alternarAmpliado">
            <slot />
        </div>

        <div v-if="verDatos && filas.length" class="mt-5 border-t border-gris-100 pt-4">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-gris-100">
                            <th
                                v-for="columna in columnas"
                                :key="columna.key"
                                class="py-2 text-left text-xs font-semibold uppercase tracking-wider text-gris-500"
                                :class="columna.alinear === 'right' ? 'pl-4 text-right' : 'pr-4'"
                            >
                                {{ columna.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(fila, index) in filas" :key="index" class="border-b border-gris-100">
                            <td
                                v-for="columna in columnas"
                                :key="columna.key"
                                class="py-2"
                                :class="columna.alinear === 'right'
                                    ? 'pl-4 text-right font-medium text-institucional-800'
                                    : 'pr-4 text-institucional-500'"
                            >
                                {{ valorCelda(fila, columna) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 border-t border-gris-100 pt-3 text-xs text-institucional-500">
            Fuente: {{ fuente }}
        </div>
    </section>
</template>
