<script setup>
// Pestaña "Cargar datos" (diseño del mockup) sobre el ETL REAL:
// archivo -> /admin/cargas/previsualizar (lee cabeceras, reconoce columnas) ->
// /admin/cargas (registra carga_archivo + job ProcesarCargaArchivo en cola).
import { ref, computed, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import CabeceraGestion from '@/Components/Gestion/CabeceraGestion.vue';

const props = defineProps({
    organizaciones: Array,
    plantillas: Array,
    perfiles: Array,
});

const page = usePage();
const erroresServidor = computed(() => page.props.errors ?? {});

const ETIQUETA_FLUJO = {
    EXPORTACION: 'Exportaciones',
    IMPORTACION: 'Importaciones',
    MERCOSUR_PAIS: 'Comercio por país (zona)',
    MERCOSUR_ITEM: 'Comercio por ítem NCM (zona)',
    FAOSTAT: 'Índices comerciales (TI)',
};
const AYUDA_FUENTE = {
    INE: 'Microdatos del INE (una fila por operación). Valores en USD. Solo .xlsx/.xlsm.',
    ALADI: 'Ranking SICOEX de Bolivia para un flujo y una gestión. Valores en MILES de USD (se convierten a USD).',
    MERCOSUR: 'Series por zona geoeconómica. La zona se toma del nombre del archivo. Recargar reemplaza (no duplica).',
    FAOSTAT: 'Descarga masiva FAOSTAT (formato largo). Índices base 2014-2016 = 100, no dólares.',
};

const orgId = ref(props.organizaciones.find((o) => o.sigla === 'INE')?.organizacion_id ?? props.organizaciones[0]?.organizacion_id);
const org = computed(() => props.organizaciones.find((o) => o.organizacion_id === orgId.value) ?? null);
const tipoFlujo = ref(org.value?.flujos?.[0] ?? 'EXPORTACION');
watch(org, (o) => {
    if (o && !o.flujos.includes(tipoFlujo.value)) tipoFlujo.value = o.flujos[0];
    reiniciar();
    if (archivo.value) asignar(archivo.value); // revalida formato y vuelve a leer
});
watch(tipoFlujo, () => { if (archivo.value) previsualizar(); });

const gestion = ref('');
const mes = ref('');
const archivo = ref(null);
const arrastrando = ref(false);
const cargando = ref(false);
const enviando = ref(false);
const progreso = ref(0);
const errorMsg = ref('');
const preview = ref(null);

const MAX_BYTES = 512000 * 1024; // 500 MB, igual que el backend
const extensiones = computed(() => org.value?.extensiones ?? ['xlsx', 'xlsm', 'csv']);
const accept = computed(() => extensiones.value.map((e) => '.' + e).join(','));

const plantillaActual = computed(() =>
    props.plantillas.find((p) => p.sigla === org.value?.sigla && p.tipo_flujo === tipoFlujo.value) ?? null
);
const otrasPlantillas = computed(() => props.plantillas.filter((p) => p !== plantillaActual.value));

const pideGestion = computed(() => ['INE', 'ALADI'].includes(org.value?.sigla));
const gestionObligatoria = computed(() => org.value?.sigla === 'ALADI');
const pideMes = computed(() => org.value?.sigla === 'INE');

function formatoTamano(b) {
    if (!b) return '';
    const u = ['B', 'KB', 'MB', 'GB']; let i = 0; let n = b;
    while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
    return `${n.toLocaleString('es-BO', { maximumFractionDigits: 1 })} ${u[i]}`;
}

function reiniciar() {
    preview.value = null;
    errorMsg.value = '';
    progreso.value = 0;
}

function asignar(f) {
    if (!f) return;
    reiniciar();
    const ext = (f.name.split('.').pop() || '').toLowerCase();
    if (!extensiones.value.includes(ext)) {
        archivo.value = null;
        errorMsg.value = ext === 'xls'
            ? `${org.value.sigla} no admite .xls con el lector actual. Abra el archivo en Excel y use "Guardar como" > Libro de Excel (.xlsx). Formatos aceptados: .${extensiones.value.join(', .')}.`
            : `Tipo no permitido (.${ext}). ${org.value.sigla} acepta: .${extensiones.value.join(', .')}.`;
        return;
    }
    if (f.size > MAX_BYTES) {
        archivo.value = null;
        errorMsg.value = `El archivo pesa ${formatoTamano(f.size)} y supera el límite de 500 MB.`;
        return;
    }
    archivo.value = f;
    previsualizar();
}

function onArchivo(e) { asignar(e.target.files[0]); e.target.value = ''; }
function onDrop(e) { arrastrando.value = false; asignar(e.dataTransfer.files?.[0]); }

async function previsualizar() {
    if (!archivo.value) return;
    cargando.value = true;
    errorMsg.value = '';
    try {
        const fd = new FormData();
        fd.append('organizacion_id', orgId.value);
        fd.append('tipo_flujo', tipoFlujo.value);
        fd.append('archivo', archivo.value);
        const { data } = await axios.post('/admin/cargas/previsualizar', fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
            onUploadProgress: (e) => { progreso.value = e.total ? Math.round((e.loaded / e.total) * 100) : 0; },
        });
        preview.value = data;
    } catch (err) {
        const errs = err.response?.data?.errors;
        errorMsg.value = errs ? Object.values(errs).flat().join(' ') : (err.response?.data?.message || 'Error al leer el archivo.');
        preview.value = null;
    } finally {
        cargando.value = false;
    }
}

const reconocidas = computed(() => preview.value?.reconocimiento?.columnas?.filter((c) => c.reconocida).length ?? 0);
const faltan = computed(() => preview.value?.reconocimiento?.faltan ?? []);
const muestra = computed(() => (preview.value?.muestra ?? []).slice(0, 6));
const puedeImportar = computed(() =>
    preview.value && !enviando.value && faltan.value.length === 0 && (!gestionObligatoria.value || gestion.value)
);

function importar() {
    if (!puedeImportar.value) return;
    enviando.value = true;
    router.post('/admin/cargas', {
        token: preview.value.token,
        organizacion_id: orgId.value,
        perfil_id: preview.value.perfil_id,
        tipo_flujo: tipoFlujo.value,
        nombre_archivo: archivo.value.name,
        gestion: gestion.value || null,
        mes: mes.value === '' ? null : mes.value,
        // Mapeo propuesto (solo lo usa el ETL genérico; los cargadores INE/ALADI/
        // MERCOSUR/FAOSTAT leen las columnas por nombre de cabecera).
        columnas: preview.value.propuesta.map((c) => ({
            origen: c.origen,
            campo_canonico: c.a_extra ? null : (c.campo_canonico || null),
            guardar: !!c.guardar && !c.a_extra,
            a_extra: !!c.a_extra,
        })),
    }, { onFinish: () => (enviando.value = false) });
}
</script>

<template>
    <Head title="Gestión de datos · Cargar" />

    <div class="max-w-7xl mx-auto">
        <CabeceraGestion activa="cargar" />

        <div v-if="errorMsg || Object.keys(erroresServidor).length" class="mb-4 px-4 py-3 rounded border border-red-200 bg-red-50 text-sm text-red-700">
            <div v-if="errorMsg">{{ errorMsg }}</div>
            <div v-for="(m, k) in erroresServidor" :key="k">{{ m }}</div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[26rem_minmax(0,1fr)] gap-4 items-start">
            <!-- 1. Fuente y archivo -->
            <section class="bg-white border border-gris-200 rounded-md shadow-sm p-5">
                <h3 class="text-base font-bold text-[#12203d] mb-4">1. Fuente y archivo</h3>

                <label class="block mb-3">
                    <span class="block text-[10px] font-bold tracking-widest uppercase text-[#5e1224] mb-1">Fuente</span>
                    <select v-model="orgId" class="w-full border border-gris-200 rounded-sm px-3 py-2 text-sm font-medium">
                        <option v-for="o in organizaciones" :key="o.organizacion_id" :value="o.organizacion_id">{{ o.nombre }} ({{ o.sigla }})</option>
                    </select>
                    <span v-if="org && AYUDA_FUENTE[org.sigla]" class="block mt-1 text-[11px] text-gris-500 leading-snug">{{ AYUDA_FUENTE[org.sigla] }}</span>
                </label>

                <label class="block mb-3">
                    <span class="block text-[10px] font-bold tracking-widest uppercase text-[#5e1224] mb-1">Flujo del archivo</span>
                    <select v-model="tipoFlujo" class="w-full border border-gris-200 rounded-sm px-3 py-2 text-sm font-medium">
                        <option v-for="f in org?.flujos ?? []" :key="f" :value="f">{{ ETIQUETA_FLUJO[f] ?? f }}</option>
                    </select>
                </label>

                <div v-if="pideGestion || pideMes" class="grid grid-cols-2 gap-3 mb-3">
                    <label v-if="pideGestion" class="block">
                        <span class="block text-[10px] font-bold tracking-widest uppercase text-[#5e1224] mb-1">Gestión {{ gestionObligatoria ? '*' : '(opcional)' }}</span>
                        <input v-model="gestion" type="number" min="1900" max="2100" placeholder="2025" class="w-full border border-gris-200 rounded-sm px-3 py-2 text-sm" />
                    </label>
                    <label v-if="pideMes" class="block">
                        <span class="block text-[10px] font-bold tracking-widest uppercase text-[#5e1224] mb-1">Mes (opcional)</span>
                        <select v-model="mes" class="w-full border border-gris-200 rounded-sm px-3 py-2 text-sm">
                            <option value="">— del archivo —</option>
                            <option :value="0">0 · Anual</option>
                            <option v-for="m in 12" :key="m" :value="m">{{ m }}</option>
                        </select>
                    </label>
                    <p class="col-span-2 text-[11px] text-gris-500 leading-snug">
                        <template v-if="org?.sigla === 'INE'">Si indica la gestión, debe coincidir con la columna GESTION del archivo (si no, se rechaza sin tocar datos). Se reemplazan las filas de esa gestión y flujo.</template>
                        <template v-else>ALADI: el ranking no trae el año; indique la gestión. Recargar el mismo flujo y gestión reemplaza el anterior.</template>
                    </p>
                </div>

                <label
                    class="block border border-dashed rounded-sm p-5 text-center cursor-pointer transition"
                    :class="arrastrando ? 'border-[#5e1224] bg-rojo-50' : 'border-institucional-300 bg-gris-50 hover:bg-white'"
                    @dragover.prevent="arrastrando = true" @dragenter.prevent="arrastrando = true"
                    @dragleave.prevent="arrastrando = false" @drop.prevent="onDrop"
                >
                    <input type="file" class="hidden" :accept="accept" @change="onArchivo" />
                    <b class="block text-sm text-[#12203d]">Elige o arrastra un archivo</b>
                    <span class="block mt-1 text-xs text-gris-500">Formatos para {{ org?.sigla }}: .{{ extensiones.join(', .') }} · máx. 500 MB</span>
                </label>

                <div v-if="archivo" class="mt-3 border border-gris-200 rounded-sm p-3 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <div class="font-medium truncate">{{ archivo.name }}</div>
                            <div class="text-xs text-gris-500">{{ formatoTamano(archivo.size) }}</div>
                        </div>
                        <button class="text-xs text-gris-500 hover:text-red-600" :disabled="cargando" @click="archivo = null; reiniciar()">Quitar</button>
                    </div>
                    <div v-if="cargando" class="mt-2 h-1.5 bg-gris-100 rounded-full overflow-hidden">
                        <div class="h-full bg-[#5e1224] transition-all" :style="{ width: progreso + '%' }"></div>
                    </div>
                </div>

                <div class="mt-4 text-sm">
                    <b class="block mb-1 text-[#12203d]">¿Dónde descargar?</b>
                    <div class="flex flex-col gap-1 text-xs">
                        <a class="text-[#5e1224] underline font-semibold" href="https://www.ine.gob.bo/index.php/estadisticas-economicas/comercio-exterior/" target="_blank" rel="noopener">INE · Comercio exterior</a>
                        <a class="text-[#5e1224] underline font-semibold" href="https://anda.ine.gob.bo" target="_blank" rel="noopener">INE · Archivo Nacional de Datos (bases)</a>
                        <a class="text-[#5e1224] underline font-semibold" href="https://www.fao.org/faostat/es/#data/TI" target="_blank" rel="noopener">FAOSTAT · Índices comerciales</a>
                        <a class="text-[#5e1224] underline font-semibold" href="https://consultawebv2.aladi.org/sicoexV2/jsf/home.seam" target="_blank" rel="noopener">ALADI · SICOEX</a>
                        <a class="text-[#5e1224] underline font-semibold" href="https://estadisticas.mercosur.int" target="_blank" rel="noopener">MERCOSUR · Estadísticas</a>
                    </div>

                    <a v-if="plantillaActual" :href="`/admin/cargas/plantilla/${plantillaActual.ruta}`"
                       class="mt-3 block w-full text-center border border-gris-200 bg-gris-50 hover:bg-white rounded-sm py-2 text-xs font-bold text-[#12203d]">
                        Descargar plantilla (Excel) · {{ plantillaActual.titulo }}
                    </a>
                    <details class="mt-2 text-xs">
                        <summary class="cursor-pointer text-gris-500">Otras plantillas</summary>
                        <ul class="mt-1 space-y-0.5">
                            <li v-for="p in otrasPlantillas" :key="p.ruta">
                                <a :href="`/admin/cargas/plantilla/${p.ruta}`" class="text-[#5e1224] hover:underline">{{ p.titulo }}</a>
                            </li>
                        </ul>
                    </details>
                </div>
            </section>

            <!-- 2. Columnas reconocidas -->
            <section class="bg-white border border-gris-200 rounded-md shadow-sm p-5 min-h-[18rem] min-w-0">
                <h3 class="text-base font-bold text-[#12203d] mb-4">2. Columnas reconocidas</h3>

                <div v-if="!preview" class="border border-gris-200 bg-gris-50 rounded-sm py-6 text-center text-sm font-semibold text-gris-500">
                    {{ cargando ? 'Leyendo el archivo…' : 'Elige un archivo para ver sus columnas.' }}
                </div>

                <template v-else>
                    <p class="text-sm text-gris-600 mb-3">
                        Archivo: <b>{{ archivo?.name }}</b> · {{ preview.cabeceras.length }} columnas ·
                        <span class="text-positivo font-semibold">{{ reconocidas }} reconocidas</span> por el cargador {{ org?.sigla }}
                        <template v-if="preview.zona_detectada"> · Zona detectada: <b>{{ preview.zona_detectada }}</b></template>
                    </p>

                    <div v-if="faltan.length" class="mb-3 px-3 py-2 rounded-sm border border-red-200 bg-red-50 text-xs text-red-700">
                        Faltan columnas obligatorias: <b>{{ faltan.join(', ') }}</b>. Use la plantilla de esta fuente/flujo (la cabecera debe estar en la primera fila de la primera hoja).
                    </div>
                    <div v-else-if="!preview.con_cargador" class="mb-3 px-3 py-2 rounded-sm border border-amber-200 bg-amber-50 text-xs text-amber-800">
                        Esta organización no tiene cargador específico: se usará el ETL genérico con el perfil de mapeo.
                    </div>

                    <div class="flex flex-wrap gap-1.5 mb-4">
                        <span v-for="(c, i) in preview.reconocimiento.columnas" :key="i"
                              class="text-[11px] px-2 py-0.5 rounded-sm border font-mono"
                              :class="c.reconocida ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-gris-200 bg-gris-50 text-gris-400'"
                              :title="c.reconocida ? `Se usa como ${c.campo}` : 'El cargador ignora esta columna'">
                            {{ c.reconocida ? '✓' : '·' }} {{ c.origen || '(vacía)' }}
                        </span>
                    </div>

                    <div class="overflow-x-auto border border-gris-200 rounded-sm">
                        <table class="text-xs w-full">
                            <thead class="bg-gris-50">
                                <tr>
                                    <th v-for="(h, i) in preview.cabeceras" :key="i" class="px-2 py-1.5 text-left whitespace-nowrap font-semibold text-gris-600">{{ h }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(fila, fi) in muestra" :key="fi" class="border-t border-gris-100">
                                    <td v-for="(v, vi) in fila" :key="vi" class="px-2 py-1 whitespace-nowrap">{{ v }}</td>
                                </tr>
                                <tr v-if="!muestra.length"><td :colspan="preview.cabeceras.length || 1" class="px-2 py-3 text-center text-gris-400">Sin filas de datos en la primera hoja.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-1 text-[11px] text-gris-400">Vista previa: primeras {{ muestra.length }} filas de la primera hoja.</p>

                    <div class="mt-4 flex items-center gap-3">
                        <button class="px-5 py-2 text-sm font-bold rounded-sm text-white bg-[#5e1224] hover:bg-[#3a0914] disabled:opacity-50"
                                :disabled="!puedeImportar" @click="importar">
                            {{ enviando ? 'Registrando…' : 'Importar datos' }}
                        </button>
                        <span class="text-xs text-gris-500">
                            <template v-if="gestionObligatoria && !gestion">Indique la gestión para continuar.</template>
                            <template v-else>Se registra la carga y el ETL la procesa en segundo plano (requiere el worker de colas).</template>
                        </span>
                    </div>
                </template>
            </section>
        </div>

        <p class="mt-6 pt-3 border-t border-gris-200 text-[11px] text-gris-400">
            Fuentes: INE, FAOSTAT, ALADI y MERCOSUR. Las cifras del portal salen de los datos cargados aquí.
        </p>
    </div>
</template>
