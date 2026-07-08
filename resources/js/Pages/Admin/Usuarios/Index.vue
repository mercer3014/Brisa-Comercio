<script setup>
import { ref, reactive } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    usuarios: Object,
    roles: Array,
    filtros: Object,
});

const busqueda = ref(props.filtros?.busqueda ?? '');

const mostrarModal = ref(false);
const editando = ref(null);

const form = useForm({
    nombre_usuario: '',
    correo: '',
    nombre_completo: '',
    contrasena: '',
    activo: true,
    roles: [],
});

function abrirCrear() {
    editando.value = null;
    form.reset();
    form.activo = true;
    form.clearErrors();
    mostrarModal.value = true;
}

function abrirEditar(u) {
    editando.value = u;
    form.nombre_usuario = u.nombre_usuario;
    form.correo = u.correo;
    form.nombre_completo = u.nombre_completo;
    form.contrasena = '';
    form.activo = u.activo;
    form.roles = (u.roles || []).map((r) => r.rol_id);
    form.clearErrors();
    mostrarModal.value = true;
}

function guardar() {
    if (editando.value) {
        form.put(`/admin/usuarios/${editando.value.usuario_id}`, {
            onSuccess: () => (mostrarModal.value = false),
        });
    } else {
        form.post('/admin/usuarios', {
            onSuccess: () => (mostrarModal.value = false),
        });
    }
}

function cambiarEstado(u) {
    router.patch(`/admin/usuarios/${u.usuario_id}/estado`, {}, { preserveScroll: true });
}

function buscar() {
    router.get('/admin/usuarios', { busqueda: busqueda.value }, { preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Usuarios" />

    <div class="max-w-6xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Usuarios</h1>
                <p class="text-slate-500 text-sm">Gestión de cuentas y asignacion de roles.</p>
            </div>
            <button @click="abrirCrear" class="bg-marca-700 hover:bg-marca-800 text-white px-4 py-2 rounded-lg text-sm font-medium">
                + Nuevo usuario
            </button>
        </div>

        <div class="mb-4 flex gap-2">
            <input
                v-model="busqueda"
                @keyup.enter="buscar"
                placeholder="Buscar por nombre, usuario o correo..."
                class="flex-1 max-w-md rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-marca-500"
            />
            <button @click="buscar" class="px-4 py-2 rounded-lg bg-slate-200 hover:bg-slate-300 text-sm">Buscar</button>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium">Usuario</th>
                        <th class="text-left px-4 py-3 font-medium">Nombre completo</th>
                        <th class="text-left px-4 py-3 font-medium">Correo</th>
                        <th class="text-left px-4 py-3 font-medium">Roles</th>
                        <th class="text-center px-4 py-3 font-medium">Estado</th>
                        <th class="text-right px-4 py-3 font-medium">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="u in usuarios.data" :key="u.usuario_id" class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ u.nombre_usuario }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ u.nombre_completo }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ u.correo }}</td>
                        <td class="px-4 py-3">
                            <span v-for="r in u.roles" :key="r.rol_id" class="inline-block bg-marca-100 text-marca-800 text-xs px-2 py-0.5 rounded mr-1">
                                {{ r.nombre }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span :class="u.activo ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-500'" class="text-xs px-2 py-0.5 rounded">
                                {{ u.activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button @click="abrirEditar(u)" class="text-marca-700 hover:underline mr-3">Editar</button>
                            <button @click="cambiarEstado(u)" class="text-slate-500 hover:underline">
                                {{ u.activo ? 'Desactivar' : 'Activar' }}
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!usuarios.data.length">
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">No hay usuarios.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal crear/editar -->
        <div v-if="mostrarModal" class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6">
                <h3 class="text-lg font-semibold text-slate-800 mb-4">
                    {{ editando ? 'Editar usuario' : 'Nuevo usuario' }}
                </h3>

                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Usuario</label>
                            <input v-model="form.nombre_usuario" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" />
                            <p v-if="form.errors.nombre_usuario" class="text-red-600 text-xs mt-1">{{ form.errors.nombre_usuario }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Correo</label>
                            <input v-model="form.correo" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" />
                            <p v-if="form.errors.correo" class="text-red-600 text-xs mt-1">{{ form.errors.correo }}</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Nombre completo</label>
                        <input v-model="form.nombre_completo" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" />
                        <p v-if="form.errors.nombre_completo" class="text-red-600 text-xs mt-1">{{ form.errors.nombre_completo }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            Contrasenia {{ editando ? '(dejar vacio para no cambiar)' : '' }}
                        </label>
                        <input v-model="form.contrasena" type="password" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" />
                        <p v-if="form.errors.contrasena" class="text-red-600 text-xs mt-1">{{ form.errors.contrasena }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Roles</label>
                        <div class="flex flex-wrap gap-3">
                            <label v-for="r in roles" :key="r.rol_id" class="inline-flex items-center gap-1.5 text-sm">
                                <input type="checkbox" :value="r.rol_id" v-model="form.roles" class="rounded text-marca-600" />
                                {{ r.nombre }}
                            </label>
                        </div>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.activo" class="rounded text-marca-600" />
                        Cuenta activa
                    </label>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button @click="mostrarModal = false" class="px-4 py-2 rounded-lg text-sm bg-slate-100 hover:bg-slate-200">Cancelar</button>
                    <button @click="guardar" :disabled="form.processing" class="px-4 py-2 rounded-lg text-sm bg-marca-700 hover:bg-marca-800 text-white disabled:opacity-60">
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
