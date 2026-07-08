<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    roles: Array,
    permisos: Object, // agrupados por modulo
});

const rolSeleccionado = ref(props.roles[0]?.rol_id ?? null);

const formEdit = useForm({
    nombre: '',
    descripcion: '',
    permisos: [],
});

const formNuevo = useForm({
    nombre: '',
    descripcion: '',
});

const mostrarNuevo = ref(false);

function seleccionar(rol) {
    rolSeleccionado.value = rol.rol_id;
    formEdit.nombre = rol.nombre;
    formEdit.descripcion = rol.descripcion ?? '';
    formEdit.permisos = [...rol.permisos];
    formEdit.clearErrors();
}

function rolActual() {
    return props.roles.find((r) => r.rol_id === rolSeleccionado.value);
}

// Inicializa con el primer rol
if (rolActual()) seleccionar(rolActual());

function togglePermiso(id) {
    const i = formEdit.permisos.indexOf(id);
    if (i === -1) formEdit.permisos.push(id);
    else formEdit.permisos.splice(i, 1);
}

function guardar() {
    formEdit.put(`/admin/roles/${rolSeleccionado.value}`, { preserveScroll: true });
}

function crearRol() {
    formNuevo.post('/admin/roles', {
        preserveScroll: true,
        onSuccess: () => {
            formNuevo.reset();
            mostrarNuevo.value = false;
        },
    });
}
</script>

<template>
    <Head title="Roles y permisos" />

    <div class="max-w-6xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Roles y permisos</h1>
                <p class="text-slate-500 text-sm">Define los roles y su matriz de permisos.</p>
            </div>
            <button @click="mostrarNuevo = !mostrarNuevo" class="bg-marca-700 hover:bg-marca-800 text-white px-4 py-2 rounded-lg text-sm font-medium">
                + Nuevo rol
            </button>
        </div>

        <div v-if="mostrarNuevo" class="bg-white rounded-xl border border-slate-200 p-4 mb-4 flex gap-3 items-end">
            <div class="flex-1">
                <label class="block text-xs font-medium text-slate-600 mb-1">Nombre del rol</label>
                <input v-model="formNuevo.nombre" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" />
                <p v-if="formNuevo.errors.nombre" class="text-red-600 text-xs mt-1">{{ formNuevo.errors.nombre }}</p>
            </div>
            <div class="flex-1">
                <label class="block text-xs font-medium text-slate-600 mb-1">Descripción</label>
                <input v-model="formNuevo.descripcion" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <button @click="crearRol" class="px-4 py-2 rounded-lg bg-marca-700 text-white text-sm">Crear</button>
        </div>

        <div class="grid grid-cols-12 gap-5">
            <!-- Lista de roles -->
            <div class="col-span-4 bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div v-for="rol in roles" :key="rol.rol_id"
                     @click="seleccionar(rol)"
                     class="px-4 py-3 cursor-pointer border-l-4 transition"
                     :class="rol.rol_id === rolSeleccionado ? 'border-marca-600 bg-marca-50' : 'border-transparent hover:bg-slate-50'">
                    <div class="font-medium text-slate-800">{{ rol.nombre }}</div>
                    <div class="text-xs text-slate-500">{{ rol.total }} permisos</div>
                </div>
            </div>

            <!-- Matriz de permisos del rol seleccionado -->
            <div class="col-span-8 bg-white rounded-xl border border-slate-200 p-5">
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Nombre</label>
                        <input v-model="formEdit.nombre" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" />
                        <p v-if="formEdit.errors.nombre" class="text-red-600 text-xs mt-1">{{ formEdit.errors.nombre }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Descripción</label>
                        <input v-model="formEdit.descripcion" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                </div>

                <div class="space-y-4 max-h-96 overflow-y-auto pr-2">
                    <div v-for="(lista, modulo) in permisos" :key="modulo">
                        <div class="text-xs font-semibold uppercase text-slate-400 mb-1.5">{{ modulo }}</div>
                        <div class="grid grid-cols-2 gap-2">
                            <label v-for="p in lista" :key="p.permiso_id" class="inline-flex items-start gap-2 text-sm text-slate-700">
                                <input type="checkbox"
                                       :checked="formEdit.permisos.includes(p.permiso_id)"
                                       @change="togglePermiso(p.permiso_id)"
                                       class="mt-0.5 rounded text-marca-600" />
                                <span>
                                    <span class="font-mono text-xs text-marca-700">{{ p.codigo }}</span>
                                    <span class="block text-xs text-slate-500">{{ p.descripcion }}</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end mt-5">
                    <button @click="guardar" :disabled="formEdit.processing" class="px-5 py-2 rounded-lg bg-marca-700 hover:bg-marca-800 text-white text-sm disabled:opacity-60">
                        Guardar cambios
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
