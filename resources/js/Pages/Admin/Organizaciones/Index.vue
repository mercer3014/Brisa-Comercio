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
        <div class="flex items-start justify-between mb-7">
            <div>
                <h1 class="text-2xl font-bold text-institucional-900 tracking-tight">Organizaciones</h1>
                <p class="text-gris-500 text-sm mt-1">Fuentes estadísticas centralizadas en Geodata.</p>
            </div>
            <button @click="abrirCrear" class="btn btn-primario shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Nueva organización
            </button>
        </div>

        <div class="tarjeta overflow-hidden">
            <table class="tabla-admin">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nombre</th>
                        <th>Sigla</th>
                        <th>País</th>
                        <th class="centro">Perfiles</th>
                        <th class="centro">Cargas</th>
                        <th class="centro">Estado</th>
                        <th class="derecha">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="o in organizaciones" :key="o.organizacion_id">
                        <td class="muted text-xs">{{ o.organizacion_id }}</td>
                        <td class="font-semibold">{{ o.nombre }}</td>
                        <td class="muted">{{ o.sigla }}</td>
                        <td class="muted font-mono text-xs">{{ o.pais_iso3 }}</td>
                        <td class="centro muted">{{ o.perfiles_count }}</td>
                        <td class="centro muted">{{ o.cargas_count }}</td>
                        <td class="centro">
                            <span :class="o.activo ? 'badge badge-ok' : 'badge badge-neutro'">
                                {{ o.activo ? 'Activa' : 'Inactiva' }}
                            </span>
                        </td>
                        <td class="derecha">
                            <button
                                @click="abrirEditar(o)"
                                class="inline-flex items-center gap-1 text-xs font-semibold text-rojo-700 hover:text-rojo-800 hover:underline"
                            >
                                Editar
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!organizaciones.length">
                        <td colspan="8" class="px-5 py-10 text-center text-gris-400 text-sm">No hay organizaciones registradas.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal -->
        <div v-if="mostrarModal" class="modal-overlay" @click.self="mostrarModal = false">
            <div class="modal-caja">
                <h3 class="modal-titulo">
                    {{ editando ? 'Editar organización' : 'Nueva organización' }}
                </h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gris-600 mb-1.5">Nombre</label>
                        <input v-model="form.nombre" class="campo" :class="{ 'campo-error': form.errors.nombre }" />
                        <p v-if="form.errors.nombre" class="text-negativo text-xs mt-1">{{ form.errors.nombre }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gris-600 mb-1.5">Sigla</label>
                            <input v-model="form.sigla" class="campo" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gris-600 mb-1.5">País (ISO3)</label>
                            <input v-model="form.pais_iso3" maxlength="3" class="campo uppercase" />
                            <p v-if="form.errors.pais_iso3" class="text-negativo text-xs mt-1">{{ form.errors.pais_iso3 }}</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gris-600 mb-1.5">URL</label>
                        <input v-model="form.url" class="campo" />
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm font-medium text-institucional-800 cursor-pointer">
                        <input type="checkbox" v-model="form.activo" class="rounded text-rojo-600" />
                        Activa
                    </label>
                </div>
                <div class="modal-acciones">
                    <button @click="mostrarModal = false" class="btn btn-secundario">Cancelar</button>
                    <button @click="guardar" :disabled="form.processing" class="btn btn-primario">
                        {{ form.processing ? 'Guardando...' : 'Guardar' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
