<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    organizaciones: Array,
    perfiles: Array,
    mercosurPaises: { type: Array, default: () => [] },
    mercosurZonas: { type: Array, default: () => [] },
});

const paso = ref(1);
const cargando = ref(false);
const errorMsg = ref('');
const arrastrando = ref(false);
const progreso = ref(0); // % de subida (0-100)

const mercosurOrganizacion = computed(() =>
    props.organizaciones.find((o) => o.sigla === 'MERCOSUR')
);

const mercosurPaises = computed(() => props.mercosurPaises ?? []);
const mercosurZonas = computed(() => props.mercosurZonas ?? []);

const seleccion = ref({
    organizacion_id: props.organizaciones[0]?.organizacion_id ?? null,
    tipo_carga: 'COMERCIO_EXTERIOR',
    tipo_flujo: 'EXPORTACION',
    mercosur_tipo: 'MERCOSUR_PAIS',
    pais_reportante_id: props.mercosurPaises?.[0]?.pais_id ?? null,
    zona_id: null,
    archivo: null,
    nombre_archivo: '',
    tamano: 0,
});

const preview = ref(null); // respuesta de /previsualizar
const columnas = ref([]);  // mapeo editable
const gestion = ref('');
const mes = ref('');

// Tipos y tamanio permitidos (coinciden con la validacion del backend: mimes + max 500 MB).
const EXTENSIONES = ['xlsx', 'xlsm', 'csv', 'txt'];
const MAX_BYTES = 512000 * 1024; // 500 MB

const esMercosur = computed(() => seleccion.value.tipo_carga === 'MERCOSUR');
const flujoActual = computed(() => esMercosur.value ? seleccion.value.mercosur_tipo : seleccion.value.tipo_flujo);
const destinoMercosur = computed(() =>
    seleccion.value.mercosur_tipo === 'MERCOSUR_PAIS'
        ? 'serie_comercio_zona'
        : 'serie_comercio_producto_zona'
);
const paisReportanteSeleccionado = computed(() =>
    mercosurPaises.value.find((p) => p.pais_id === seleccion.value.pais_reportante_id)
);
const zonaSeleccionada = computed(() =>
    mercosurZonas.value.find((z) => z.zona_id === seleccion.value.zona_id)
);

function formatoTamano(bytes) {
    if (!bytes) return '';
    const u = ['B', 'KB', 'MB', 'GB'];
    let i = 0; let n = bytes;
    while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
    return `${n.toLocaleString('es-BO', { maximumFractionDigits: 1 })} ${u[i]}`;
}

function reiniciarPreview() {
    preview.value = null;
    columnas.value = [];
    if (paso.value !== 1) paso.value = 1;
}

watch(() => seleccion.value.tipo_carga, (tipo) => {
    if (tipo === 'MERCOSUR') {
        if (mercosurOrganizacion.value) {
            seleccion.value.organizacion_id = mercosurOrganizacion.value.organizacion_id;
        }
        seleccion.value.tipo_flujo = seleccion.value.mercosur_tipo;
        if (!seleccion.value.pais_reportante_id && mercosurPaises.value.length) {
            seleccion.value.pais_reportante_id = mercosurPaises.value[0].pais_id;
        }
    } else if (!['EXPORTACION', 'IMPORTACION'].includes(seleccion.value.tipo_flujo)) {
        seleccion.value.tipo_flujo = 'EXPORTACION';
    }
    reiniciarPreview();
});

watch(() => seleccion.value.mercosur_tipo, (tipo) => {
    if (esMercosur.value) {
        seleccion.value.tipo_flujo = tipo;
        reiniciarPreview();
    }
});

/**
 * Valida tipo y tamanio. Devuelve un mensaje de error o null si es valido.
 */
function validarArchivo(f) {
    const ext = (f.name.split('.').pop() || '').toLowerCase();
    if (!EXTENSIONES.includes(ext)) {
        return `Tipo no permitido (.${ext}). Use un archivo .xlsx, .xlsm, .csv o .txt.`;
    }
    if (f.size > MAX_BYTES) {
        return `El archivo pesa ${formatoTamano(f.size)} y supera el limite de 500 MB.`;
    }
    return null;
}

function asignarArchivo(f) {
    if (!f) return;
    const error = validarArchivo(f);
    if (error) {
        errorMsg.value = error;
        return;
    }
    errorMsg.value = '';
    progreso.value = 0;
    seleccion.value.archivo = f;
    seleccion.value.nombre_archivo = f.name;
    seleccion.value.tamano = f.size;
    reiniciarPreview();
}

function onArchivo(e) {
    asignarArchivo(e.target.files[0]);
    e.target.value = ''; // permite re-seleccionar el mismo archivo
}

function onDrop(e) {
    arrastrando.value = false;
    asignarArchivo(e.dataTransfer.files?.[0]);
}

function quitarArchivo() {
    seleccion.value.archivo = null;
    seleccion.value.nombre_archivo = '';
    seleccion.value.tamano = 0;
    progreso.value = 0;
    reiniciarPreview();
}

async function previsualizar() {
    if (!seleccion.value.archivo) {
        errorMsg.value = 'Seleccione o arrastre un archivo.';
        return;
    }
    if (esMercosur.value && !seleccion.value.pais_reportante_id) {
        errorMsg.value = 'Seleccione el pais reportante para MERCOSUR.';
        return;
    }
    errorMsg.value = '';
    cargando.value = true;
    progreso.value = 0;
    try {
        const fd = new FormData();
        fd.append('organizacion_id', seleccion.value.organizacion_id);
        fd.append('tipo_flujo', flujoActual.value);
        fd.append('archivo', seleccion.value.archivo);
        const { data } = await axios.post('/admin/cargas/previsualizar', fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
            onUploadProgress: (e) => {
                progreso.value = e.total ? Math.round((e.loaded / e.total) * 100) : 0;
            },
        });
        preview.value = data;
        columnas.value = data.propuesta.map((p) => ({ ...p }));
        paso.value = 2;
    } catch (err) {
        errorMsg.value = err.response?.data?.message || 'Error al leer el archivo.';
    } finally {
        cargando.value = false;
    }
}

const camposOpciones = computed(() => preview.value?.campos ?? CAMPOS);

// Lista de campos canonicos (debe coincidir con config/comexhub.php)
const CAMPOS = [
    'gestion', 'mes', 'tipo_operacion', 'flujo', 'codigo_nandina', 'descripcion_producto',
    'codigo_capitulo', 'codigo_seccion', 'codigo_cuci', 'codigo_gce', 'codigo_ciiu',
    'codigo_grupo_actividad', 'codigo_tnt', 'codigo_cuode', 'codigo_pais', 'codigo_zona',
    'codigo_departamento', 'codigo_medio', 'codigo_via', 'codigo_aduana',
    'peso_bruto_kg', 'peso_neto_kg', 'peso_fino_kg', 'valor_fob_usd',
    'valor_cif_frontera_usd', 'valor_cif_aduana_usd', 'gravamenes_pagados',
];

const desconocidasSinDecidir = computed(() =>
    columnas.value.filter((c) => c.desconocida && c.guardar && !c.campo_canonico && !c.a_extra)
);

function confirmar() {
    cargando.value = true;

    const payload = {
        token: preview.value.token,
        organizacion_id: seleccion.value.organizacion_id,
        perfil_id: esMercosur.value ? null : preview.value.perfil_id,
        tipo_flujo: flujoActual.value,
        nombre_archivo: seleccion.value.nombre_archivo,
        gestion: esMercosur.value ? null : (gestion.value || null),
        mes: esMercosur.value ? null : (mes.value || null),
        columnas: esMercosur.value ? [] : columnas.value.map((c) => ({
            origen: c.origen,
            campo_canonico: c.a_extra ? null : (c.campo_canonico || null),
            guardar: !!c.guardar && !c.a_extra,
            a_extra: !!c.a_extra,
        })),
    };

    if (esMercosur.value) {
        payload.pais_reportante_id = seleccion.value.pais_reportante_id;
        payload.zona_id = seleccion.value.zona_id || null;
    }

    router.post('/admin/cargas', payload, {
        onFinish: () => (cargando.value = false),
    });
}
</script>

<template>
    <Head title="Nueva carga" />

    <div class="max-w-6xl mx-auto">
        <h1 class="text-2xl font-bold text-slate-800 mb-1">Nueva carga de archivo</h1>
        <p class="text-slate-500 text-sm mb-6">Sube un Excel/CSV, revisa el mapeo y elige que columnas guardar.</p>

        <div v-if="errorMsg" class="mb-4 px-4 py-3 rounded bg-red-50 border border-red-200 text-red-700 text-sm">{{ errorMsg }}</div>

        <!-- PASO 1: seleccion -->
        <div v-if="paso === 1" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 max-w-2xl">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Organizacion</label>
                    <select v-model="seleccion.organizacion_id" :disabled="esMercosur" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100 disabled:text-slate-500">
                        <option v-for="o in organizaciones" :key="o.organizacion_id" :value="o.organizacion_id">{{ o.nombre }}</option>
                    </select>
                    <p v-if="esMercosur" class="text-xs text-slate-400 mt-1">MERCOSUR usa su organizacion propia para no mezclar fuentes.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tipo de carga</label>
                    <select v-model="seleccion.tipo_carga" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="COMERCIO_EXTERIOR">Comercio exterior</option>
                        <option value="MERCOSUR">MERCOSUR</option>
                    </select>
                </div>
                <div v-if="!esMercosur">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tipo de flujo</label>
                    <select v-model="seleccion.tipo_flujo" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="EXPORTACION">EXPORTACION</option>
                        <option value="IMPORTACION">IMPORTACION</option>
                    </select>
                </div>
                <div v-if="esMercosur">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tipo de archivo MERCOSUR</label>
                    <select v-model="seleccion.mercosur_tipo" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="MERCOSUR_PAIS">Por paises</option>
                        <option value="MERCOSUR_ITEM">Items NCM</option>
                    </select>
                </div>
                <div v-if="esMercosur">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Pais reportante</label>
                    <select v-model="seleccion.pais_reportante_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option v-for="p in mercosurPaises" :key="p.pais_id" :value="p.pais_id">{{ p.nombre }}</option>
                    </select>
                </div>
                <div v-if="esMercosur">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Zona comercial</label>
                    <select v-model="seleccion.zona_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option :value="null">Inferir desde el nombre del archivo</option>
                        <option v-for="z in mercosurZonas" :key="z.zona_id" :value="z.zona_id">{{ z.descripcion }}</option>
                    </select>
                </div>
            </div>

            <div v-if="esMercosur" class="mb-5 px-4 py-3 rounded-lg bg-sky-50 border border-sky-200 text-sky-800 text-sm">
                Esta carga usa el ETL MERCOSUR. No requiere mapeo manual: guardara los datos en <span class="font-mono">{{ destinoMercosur }}</span>.
            </div>

            <div class="mb-5">
                <label class="block text-sm font-medium text-slate-700 mb-1">Archivo (.xlsx, .xlsm, .csv o .txt)</label>

                <!-- Zona de arrastrar y soltar -->
                <div
                    @dragover.prevent="arrastrando = true"
                    @dragenter.prevent="arrastrando = true"
                    @dragleave.prevent="arrastrando = false"
                    @drop.prevent="onDrop"
                    class="border-2 border-dashed rounded-xl p-6 text-center transition"
                    :class="arrastrando ? 'border-marca-500 bg-marca-50' : 'border-slate-300 bg-slate-50'"
                >
                    <svg class="w-10 h-10 mx-auto text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.9A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                    <p class="mt-2 text-sm text-slate-600">
                        Arrastra el archivo aqui o
                        <label class="text-marca-700 font-medium cursor-pointer hover:underline">
                            selecciona uno
                            <input type="file" accept=".xlsx,.xlsm,.csv,.txt" @change="onArchivo" class="hidden" />
                        </label>
                    </p>
                    <p class="text-xs text-slate-400 mt-1">Formatos: .xlsx, .xlsm, .csv, .txt - maximo 500 MB</p>
                </div>

                <!-- Archivo seleccionado + progreso -->
                <div v-if="seleccion.archivo" class="mt-3 bg-white border border-slate-200 rounded-lg p-3">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-slate-800 truncate">{{ seleccion.nombre_archivo }}</div>
                            <div class="text-xs text-slate-500">{{ formatoTamano(seleccion.tamano) }}</div>
                        </div>
                        <button @click="quitarArchivo" :disabled="cargando" class="text-xs text-slate-500 hover:text-red-600 disabled:opacity-40">Quitar</button>
                    </div>
                    <div v-if="cargando || progreso > 0" class="mt-2">
                        <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full bg-marca-600 transition-all" :style="{ width: progreso + '%' }"></div>
                        </div>
                        <div class="text-xs text-slate-400 mt-1 text-right">{{ progreso }}%</div>
                    </div>
                </div>
            </div>
            <button @click="previsualizar" :disabled="cargando || !seleccion.archivo" class="bg-marca-700 hover:bg-marca-800 text-white px-5 py-2.5 rounded-lg text-sm font-medium disabled:opacity-60">
                {{ cargando ? 'Leyendo...' : 'Previsualizar' }}
            </button>
        </div>

        <!-- PASO 2: previsualizacion + mapeo -->
        <div v-if="paso === 2 && preview" class="space-y-5">
            <template v-if="!esMercosur">
                <!-- Deteccion -->
                <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-center justify-between">
                    <div>
                        <div class="text-sm text-slate-500">Perfil detectado</div>
                        <div class="font-medium text-marca-800">
                            <template v-if="preview.deteccion.mejor">
                                Perfil #{{ preview.deteccion.mejor.perfil_id }} -
                                score {{ preview.deteccion.mejor.score }}% -
                                {{ preview.deteccion.mejor.coincidencias }}/{{ preview.deteccion.mejor.total_perfil }} columnas
                            </template>
                            <template v-else>Ningun perfil coincidente (mapeo manual)</template>
                        </div>
                    </div>
                    <button @click="paso = 1" class="text-sm text-slate-500 hover:underline">Cambiar archivo</button>
                </div>

                <!-- Aviso columnas desconocidas -->
                <div v-if="columnas.some(c => c.desconocida)" class="px-4 py-3 rounded bg-amber-50 border border-amber-200 text-amber-800 text-sm">
                    Hay columnas que no estan en el perfil. Decide para cada una: mapear a un campo, enviar a "extra" o ignorar (desmarcar guardar).
                </div>

                <!-- Tabla de mapeo -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="text-left px-3 py-2 font-medium">Columna del archivo</th>
                                <th class="text-left px-3 py-2 font-medium">Campo canonico</th>
                                <th class="text-center px-3 py-2 font-medium">Guardar</th>
                                <th class="text-center px-3 py-2 font-medium">A extra (JSON)</th>
                                <th class="text-left px-3 py-2 font-medium">Muestra</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(c, i) in columnas" :key="i" :class="c.desconocida ? 'bg-amber-50/40' : ''">
                                <td class="px-3 py-2 font-mono text-xs text-slate-700">
                                    {{ c.origen }}
                                    <span v-if="c.desconocida" class="ml-1 text-amber-600" title="No esta en el perfil">*</span>
                                </td>
                                <td class="px-3 py-2">
                                    <select v-model="c.campo_canonico" :disabled="c.a_extra" class="w-full rounded border border-slate-300 px-2 py-1 text-sm disabled:bg-slate-100">
                                        <option :value="null">-- (sin mapear) --</option>
                                        <option v-for="campo in camposOpciones" :key="campo" :value="campo">{{ campo }}</option>
                                    </select>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <input type="checkbox" v-model="c.guardar" :disabled="c.a_extra" class="rounded text-marca-600" />
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <input type="checkbox" v-model="c.a_extra" class="rounded text-marca-600" />
                                </td>
                                <td class="px-3 py-2 text-slate-500 text-xs truncate max-w-[160px]">
                                    {{ (preview.muestra[0] && preview.muestra[0][i]) ?? '' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>

            <div v-else class="bg-white rounded-xl border border-slate-200 p-4 flex items-center justify-between">
                <div>
                    <div class="text-sm text-slate-500">Carga MERCOSUR</div>
                    <div class="font-medium text-marca-800">
                        {{ seleccion.mercosur_tipo === 'MERCOSUR_PAIS' ? 'Por paises' : 'Items NCM' }} -> {{ destinoMercosur }}
                    </div>
                    <div class="text-sm text-slate-500 mt-1">
                        Pais reportante: {{ paisReportanteSeleccionado?.nombre ?? 'No seleccionado' }}.
                        Zona: {{ zonaSeleccionada?.descripcion ?? 'Se inferira desde el nombre del archivo' }}.
                    </div>
                </div>
                <button @click="paso = 1" class="text-sm text-slate-500 hover:underline">Cambiar archivo</button>
            </div>

            <!-- Muestra de datos -->
            <details class="bg-white rounded-xl border border-slate-200 p-4">
                <summary class="cursor-pointer text-sm font-medium text-slate-700">Ver muestra de datos ({{ preview.muestra.length }} filas)</summary>
                <div class="overflow-x-auto mt-3">
                    <table class="text-xs border-collapse">
                        <thead>
                            <tr>
                                <th v-for="(h, i) in preview.cabeceras" :key="i" class="border border-slate-200 px-2 py-1 bg-slate-50 text-left whitespace-nowrap">{{ h }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(fila, fi) in preview.muestra" :key="fi">
                                <td v-for="(val, vi) in fila" :key="vi" class="border border-slate-100 px-2 py-1 whitespace-nowrap">{{ val }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </details>

            <!-- Metadatos opcionales + confirmar -->
            <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-end gap-4">
                <template v-if="!esMercosur">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Gestion (opcional)</label>
                        <input v-model="gestion" type="number" placeholder="2024" class="w-28 rounded border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Mes (opcional)</label>
                        <input v-model="mes" type="number" min="1" max="12" placeholder="1-12" class="w-24 rounded border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                </template>
                <div v-else class="text-sm text-slate-500">
                    El anio se leera desde la columna correspondiente del Excel y se guardara como periodo anual.
                </div>
                <div class="ml-auto">
                    <button @click="confirmar" :disabled="cargando" class="bg-marca-700 hover:bg-marca-800 text-white px-6 py-2.5 rounded-lg text-sm font-medium disabled:opacity-60">
                        {{ cargando ? 'Registrando...' : 'Confirmar y encolar' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
