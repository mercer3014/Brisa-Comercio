<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    organizaciones: Array,
});

const mostrarModal = ref(false);
const editando = ref(null);

const form = useForm({
    nombre: '',
    sigla: '',
    pais_iso3: '',
    url: '',
    activo: true,
});

function abrirCrear() {
    editando.value = null;
    form.reset();
    form.activo = true;
    form.clearErrors();
    mostrarModal.value = true;
}

function abrirEditar(o) {
    editando.value = o;
    form.nombre = o.nombre;
    form.sigla = o.sigla ?? '';
    form.pais_iso3 = o.pais_iso3 ?? '';
    form.url = o.url ?? '';
    form.activo = o.activo;
    form.clearErrors();
    mostrarModal.value = true;
}

function guardar() {
    if (editando.value) {
        form.put(`/admin/organizaciones/${editando.value.organizacion_id}`, {
            preserveScroll: true,
            onSuccess: () => (mostrarModal.value = false),
        });
    } else {
        form.post('/admin/organizaciones', {
            preserveScroll: true,
            onSuccess: () => (mostrarModal.value = false),
        });
    }
}
</script>

<template>
    <Head title="Organizaciones" />

    <div class="max-w-5xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Organizaciones</h1>
                <p class="text-slate-500 text-sm">Fuentes estadisticas centralizadas en ComexHub.</p>
            </div>
            <button @click="abrirCrear" class="bg-marca-700 hover:bg-marca-800 text-white px-4 py-2 rounded-lg text-sm font-medium">
                + Nueva organizacion
            </button>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium">#</th>
                        <th class="text-left px-4 py-3 font-medium">Nombre</th>
                        <th class="text-left px-4 py-3 font-medium">Sigla</th>
                        <th class="text-left px-4 py-3 font-medium">Pais</th>
                        <th class="text-center px-4 py-3 font-medium">Perfiles</th>
                        <th class="text-center px-4 py-3 font-medium">Cargas</th>
                        <th class="text-center px-4 py-3 font-medium">Estado</th>
                        <th class="text-right px-4 py-3 font-medium">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="o in organizaciones" :key="o.organizacion_id" class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-slate-400">{{ o.organizacion_id }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800">{{ o.nombre }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ o.sigla }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ o.pais_iso3 }}</td>
                        <td class="px-4 py-3 text-center text-slate-600">{{ o.perfiles_count }}</td>
                        <td class="px-4 py-3 text-center text-slate-600">{{ o.cargas_count }}</td>
                        <td class="px-4 py-3 text-center">
                            <span :class="o.activo ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-500'" class="text-xs px-2 py-0.5 rounded">
                                {{ o.activo ? 'Activa' : 'Inactiva' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button @click="abrirEditar(o)" class="text-marca-700 hover:underline">Editar</button>
                        </td>
                    </tr>
                    <tr v-if="!organizaciones.length">
                        <td colspan="8" class="px-4 py-8 text-center text-slate-400">No hay organizaciones.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="mostrarModal" class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
                <h3 class="text-lg font-semibold text-slate-800 mb-4">
                    {{ editando ? 'Editar organizacion' : 'Nueva organizacion' }}
                </h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Nombre</label>
                        <input v-model="form.nombre" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" />
                        <p v-if="form.errors.nombre" class="text-red-600 text-xs mt-1">{{ form.errors.nombre }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Sigla</label>
                            <input v-model="form.sigla" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Pais (ISO3)</label>
                            <input v-model="form.pais_iso3" maxlength="3" class="w-full rounded border border-slate-300 px-3 py-2 text-sm uppercase" />
                            <p v-if="form.errors.pais_iso3" class="text-red-600 text-xs mt-1">{{ form.errors.pais_iso3 }}</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">URL</label>
                        <input v-model="form.url" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.activo" class="rounded text-marca-600" /> Activa
                    </label>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button @click="mostrarModal = false" class="px-4 py-2 rounded-lg text-sm bg-slate-100 hover:bg-slate-200">Cancelar</button>
                    <button @click="guardar" :disabled="form.processing" class="px-4 py-2 rounded-lg text-sm bg-marca-700 hover:bg-marca-800 text-white disabled:opacity-60">Guardar</button>
                </div>
            </div>
        </div>
    </div>
</template>
