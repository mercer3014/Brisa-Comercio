<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    perfiles: Array,
    organizaciones: Array,
});

const mostrarNuevo = ref(false);
const form = useForm({
    organizacion_id: props.organizaciones[0]?.organizacion_id ?? null,
    tipo_flujo: 'EXPORTACION',
    etiqueta_version: '',
    descripcion: '',
});

function crear() {
    form.post('/admin/perfiles', { onSuccess: () => (mostrarNuevo.value = false) });
}

function editar(p) {
    router.get(`/admin/perfiles/${p.perfil_id}/editar`);
}

// --- Detector de perfil ---
const textoCabeceras = ref('');
const resultado = ref(null);
const detectando = ref(false);

async function detectar() {
    const cabeceras = textoCabeceras.value
        .split(/[\n,;\t]+/)
        .map((s) => s.trim())
        .filter(Boolean);
    if (!cabeceras.length) return;
    detectando.value = true;
    resultado.value = null;
    try {
        const { data } = await axios.post('/admin/perfiles/detectar', { cabeceras });
        resultado.value = data;
    } finally {
        detectando.value = false;
    }
}

function nombrePerfil(perfilId) {
    const p = props.perfiles.find((x) => x.perfil_id === perfilId);
    return p ? `${p.organizacion?.sigla ?? ''} · ${p.tipo_flujo} · ${p.etiqueta_version}` : `Perfil ${perfilId}`;
}
</script>

<template>
    <Head title="Perfiles de mapeo" />

    <div class="max-w-6xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Perfiles de mapeo</h1>
                <p class="text-slate-500 text-sm">Traducen cabeceras de archivos a campos canonicos.</p>
            </div>
            <button @click="mostrarNuevo = !mostrarNuevo" class="bg-marca-700 hover:bg-marca-800 text-white px-4 py-2 rounded-lg text-sm font-medium">
                + Nuevo perfil
            </button>
        </div>

        <div v-if="mostrarNuevo" class="bg-white rounded-xl border border-slate-200 p-4 mb-5 grid grid-cols-4 gap-3 items-end">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Organizacion</label>
                <select v-model="form.organizacion_id" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    <option v-for="o in organizaciones" :key="o.organizacion_id" :value="o.organizacion_id">{{ o.nombre }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Tipo de flujo</label>
                <select v-model="form.tipo_flujo" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    <option value="EXPORTACION">EXPORTACION</option>
                    <option value="IMPORTACION">IMPORTACION</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Etiqueta version</label>
                <input v-model="form.etiqueta_version" placeholder="ej. INE-EXPO-2024" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" />
                <p v-if="form.errors.etiqueta_version" class="text-red-600 text-xs mt-1">{{ form.errors.etiqueta_version }}</p>
            </div>
            <button @click="crear" :disabled="form.processing" class="px-4 py-2 rounded-lg bg-marca-700 text-white text-sm">Crear y mapear</button>
        </div>

        <div class="grid grid-cols-12 gap-5">
            <!-- Lista de perfiles -->
            <div class="col-span-7 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="text-left px-4 py-3 font-medium">Organizacion</th>
                            <th class="text-left px-4 py-3 font-medium">Flujo</th>
                            <th class="text-left px-4 py-3 font-medium">Version</th>
                            <th class="text-center px-4 py-3 font-medium">Columnas</th>
                            <th class="text-center px-4 py-3 font-medium">Activo</th>
                            <th class="text-right px-4 py-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="p in perfiles" :key="p.perfil_id" class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-slate-700">{{ p.organizacion?.sigla || p.organizacion?.nombre }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs px-2 py-0.5 rounded" :class="p.tipo_flujo === 'EXPORTACION' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                                    {{ p.tipo_flujo }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-700">{{ p.etiqueta_version }}</td>
                            <td class="px-4 py-3 text-center text-slate-600">{{ p.columnas_count }}</td>
                            <td class="px-4 py-3 text-center">
                                <span :class="p.activo ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-500'" class="text-xs px-2 py-0.5 rounded">
                                    {{ p.activo ? 'Si' : 'No' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button @click="editar(p)" class="text-marca-700 hover:underline">Editar mapeo</button>
                            </td>
                        </tr>
                        <tr v-if="!perfiles.length">
                            <td colspan="6" class="px-4 py-8 text-center text-slate-400">No hay perfiles.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Detector de perfil -->
            <div class="col-span-5 bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                <h3 class="font-semibold text-slate-700 mb-1">Detector de perfil</h3>
                <p class="text-xs text-slate-500 mb-3">Pega las cabeceras de un archivo (separadas por coma o salto de linea) y se sugiere el perfil que mejor coincide.</p>
                <textarea v-model="textoCabeceras" rows="4" placeholder="GESTION, MES, NANDINA, PAIS, KILBRU, FOB..."
                          class="w-full rounded border border-slate-300 px-3 py-2 text-sm font-mono"></textarea>
                <button @click="detectar" :disabled="detectando" class="mt-2 w-full px-4 py-2 rounded-lg bg-marca-700 hover:bg-marca-800 text-white text-sm disabled:opacity-60">
                    {{ detectando ? 'Detectando...' : 'Detectar perfil' }}
                </button>

                <div v-if="resultado" class="mt-4">
                    <div v-if="resultado.mejor" class="p-3 rounded-lg bg-marca-50 border border-marca-200 mb-3">
                        <div class="text-xs text-slate-500">Mejor coincidencia</div>
                        <div class="font-medium text-marca-800">{{ nombrePerfil(resultado.mejor.perfil_id) }}</div>
                        <div class="text-sm text-slate-600">
                            Score <strong>{{ resultado.mejor.score }}%</strong> ·
                            {{ resultado.mejor.coincidencias }}/{{ resultado.mejor.total_perfil }} columnas
                        </div>
                    </div>
                    <div v-else class="text-sm text-slate-400">No se encontraron perfiles coincidentes.</div>

                    <div v-if="resultado.candidatos && resultado.candidatos.length > 1" class="text-xs text-slate-500">
                        <div class="font-medium mb-1">Otros candidatos:</div>
                        <div v-for="c in resultado.candidatos.slice(1)" :key="c.perfil_id" class="flex justify-between py-0.5">
                            <span>{{ nombrePerfil(c.perfil_id) }}</span>
                            <span>{{ c.score }}%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
