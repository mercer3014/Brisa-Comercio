<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    perfil: Object,
    columnas: Array,
    camposCanonicos: Object, // { campo: {etiqueta, grupo, tipo} }
});

// Campos canonicos agrupados para el <optgroup>
const gruposCampos = computed(() => {
    const grupos = {};
    for (const [campo, meta] of Object.entries(props.camposCanonicos)) {
        (grupos[meta.grupo] ??= []).push({ campo, ...meta });
    }
    return grupos;
});

// Formulario de cabecera del perfil
const formPerfil = useForm({
    etiqueta_version: props.perfil.etiqueta_version,
    descripcion: props.perfil.descripcion ?? '',
    activo: props.perfil.activo,
});

// Tabla editable de columnas
const filas = ref(
    props.columnas.map((c) => ({
        nombre_columna_origen: c.nombre_columna_origen,
        campo_canonico: c.campo_canonico ?? '',
        guardar: c.guardar,
        a_extra: c.a_extra,
        nota: c.nota ?? '',
    }))
);

function agregarFila() {
    filas.value.push({ nombre_columna_origen: '', campo_canonico: '', guardar: true, a_extra: false, nota: '' });
}

function eliminarFila(i) {
    filas.value.splice(i, 1);
}

const guardando = ref(false);

function guardarCabecera() {
    formPerfil.put(`/admin/perfiles/${props.perfil.perfil_id}`, { preserveScroll: true });
}

function guardarColumnas() {
    guardando.value = true;
    router.put(`/admin/perfiles/${props.perfil.perfil_id}/columnas`, { columnas: filas.value }, {
        preserveScroll: true,
        onFinish: () => (guardando.value = false),
    });
}
</script>

<template>
    <Head :title="`Mapeo · ${perfil.etiqueta_version}`" />

    <div class="max-w-6xl mx-auto">
        <Link href="/admin/perfiles" class="text-sm text-marca-700 hover:underline">&larr; Volver a perfiles</Link>

        <div class="flex items-center justify-between mt-2 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">
                    {{ perfil.organizacion?.sigla }} · {{ perfil.tipo_flujo }}
                </h1>
                <p class="text-slate-500 text-sm">Perfil <span class="font-mono">{{ perfil.etiqueta_version }}</span></p>
            </div>
        </div>

        <!-- Cabecera del perfil -->
        <div class="bg-white rounded-xl border border-slate-200 p-4 mb-5 grid grid-cols-12 gap-3 items-end">
            <div class="col-span-3">
                <label class="block text-xs font-medium text-slate-600 mb-1">Etiqueta version</label>
                <input v-model="formPerfil.etiqueta_version" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div class="col-span-6">
                <label class="block text-xs font-medium text-slate-600 mb-1">Descripcion</label>
                <input v-model="formPerfil.descripcion" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <label class="col-span-2 inline-flex items-center gap-2 text-sm pb-2">
                <input type="checkbox" v-model="formPerfil.activo" class="rounded text-marca-600" /> Activo
            </label>
            <button @click="guardarCabecera" class="col-span-1 px-3 py-2 rounded-lg bg-slate-200 hover:bg-slate-300 text-sm">Guardar</button>
        </div>

        <!-- Tabla de mapeo de columnas -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
                <h2 class="font-semibold text-slate-700">Mapeo de columnas ({{ filas.length }})</h2>
                <button @click="agregarFila" class="text-sm text-marca-700 hover:underline">+ Agregar columna</button>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="text-left px-3 py-2 font-medium">Columna origen</th>
                        <th class="text-left px-3 py-2 font-medium">Campo canonico</th>
                        <th class="text-center px-3 py-2 font-medium">Guardar</th>
                        <th class="text-center px-3 py-2 font-medium">A extra</th>
                        <th class="text-left px-3 py-2 font-medium">Nota</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="(f, i) in filas" :key="i" class="hover:bg-slate-50">
                        <td class="px-3 py-2">
                            <input v-model="f.nombre_columna_origen" class="w-full rounded border border-slate-300 px-2 py-1 text-sm font-mono" />
                        </td>
                        <td class="px-3 py-2">
                            <select v-model="f.campo_canonico" class="w-full rounded border border-slate-300 px-2 py-1 text-sm">
                                <option value="">— (sin mapear) —</option>
                                <optgroup v-for="(campos, grupo) in gruposCampos" :key="grupo" :label="grupo">
                                    <option v-for="c in campos" :key="c.campo" :value="c.campo">{{ c.etiqueta }}</option>
                                </optgroup>
                            </select>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <input type="checkbox" v-model="f.guardar" class="rounded text-marca-600" />
                        </td>
                        <td class="px-3 py-2 text-center">
                            <input type="checkbox" v-model="f.a_extra" class="rounded text-marca-600" />
                        </td>
                        <td class="px-3 py-2">
                            <input v-model="f.nota" class="w-full rounded border border-slate-300 px-2 py-1 text-sm" />
                        </td>
                        <td class="px-3 py-2 text-center">
                            <button @click="eliminarFila(i)" class="text-red-500 hover:text-red-700">✕</button>
                        </td>
                    </tr>
                    <tr v-if="!filas.length">
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">Sin columnas. Agrega una.</td>
                    </tr>
                </tbody>
            </table>
            <div class="flex justify-end px-4 py-3 border-t border-slate-100">
                <button @click="guardarColumnas" :disabled="guardando" class="px-5 py-2 rounded-lg bg-marca-700 hover:bg-marca-800 text-white text-sm disabled:opacity-60">
                    {{ guardando ? 'Guardando...' : 'Guardar mapeo' }}
                </button>
            </div>
        </div>

        <p class="text-xs text-slate-400 mt-3">
            "Guardar": la columna se almacena en su campo canonico. "A extra": se conserva en
            atributos_extra (JSONB). Sin marcar ninguna: se ignora al procesar.
        </p>
    </div>
</template>
