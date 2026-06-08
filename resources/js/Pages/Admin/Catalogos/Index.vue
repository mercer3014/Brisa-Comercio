<script setup>
import { ref } from 'vue';
import { Head, router, useForm, Link } from '@inertiajs/vue3';

const props = defineProps({
    catalogoActual: String,
    definicion: Object, // {etiqueta, pk, campos}
    catalogos: Array,
    registros: Object,
    filtros: Object,
});

const busqueda = ref(props.filtros?.busqueda ?? '');
const editando = ref(null);
const form = useForm(Object.fromEntries(props.definicion.campos.map((c) => [c, ''])));

function buscar() {
    router.get(`/admin/catalogos/${props.catalogoActual}`, { busqueda: busqueda.value }, { preserveState: true, replace: true });
}

function abrirEditar(r) {
    editando.value = r;
    props.definicion.campos.forEach((c) => (form[c] = r[c] ?? ''));
    form.clearErrors();
}

function guardar() {
    form.put(`/admin/catalogos/${props.catalogoActual}/${editando.value[props.definicion.pk]}`, {
        preserveScroll: true,
        onSuccess: () => (editando.value = null),
    });
}
</script>

<template>
    <Head :title="`Catalogo: ${definicion.etiqueta}`" />
    <div class="max-w-6xl mx-auto">
        <h1 class="text-2xl font-bold text-slate-800 mb-1">Catalogos</h1>
        <p class="text-slate-500 text-sm mb-4">Revisar y corregir dimensiones del sistema.</p>

        <!-- Selector de catalogo -->
        <div class="flex flex-wrap gap-2 mb-4">
            <Link v-for="c in catalogos" :key="c.clave" :href="`/admin/catalogos/${c.clave}`"
                  class="px-3 py-1.5 rounded-lg text-sm border"
                  :class="c.clave === catalogoActual ? 'bg-marca-700 text-white border-marca-700' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'">
                {{ c.etiqueta }}
            </Link>
        </div>

        <div class="flex gap-2 mb-4">
            <input v-model="busqueda" @keyup.enter="buscar" placeholder="Buscar..." class="flex-1 max-w-md rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            <button @click="buscar" class="px-4 py-2 rounded-lg bg-slate-200 text-sm">Buscar</button>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th v-for="c in definicion.campos" :key="c" class="text-left px-3 py-2 font-medium">{{ c }}</th>
                        <th class="text-right px-3 py-2 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="r in registros.data" :key="r[definicion.pk]" class="hover:bg-slate-50">
                        <td v-for="c in definicion.campos" :key="c" class="px-3 py-1.5">{{ r[c] }}</td>
                        <td class="px-3 py-1.5 text-right">
                            <button @click="abrirEditar(r)" class="text-marca-700 hover:underline">Editar</button>
                        </td>
                    </tr>
                    <tr v-if="!registros.data.length">
                        <td :colspan="definicion.campos.length + 1" class="px-4 py-8 text-center text-slate-400">Sin registros.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal editar -->
        <div v-if="editando" class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
                <h3 class="text-lg font-semibold text-slate-800 mb-4">Editar registro</h3>
                <div class="space-y-3">
                    <div v-for="c in definicion.campos" :key="c">
                        <label class="block text-xs font-medium text-slate-600 mb-1">{{ c }}</label>
                        <input v-model="form[c]" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button @click="editando = null" class="px-4 py-2 rounded-lg text-sm bg-slate-100">Cancelar</button>
                    <button @click="guardar" :disabled="form.processing" class="px-4 py-2 rounded-lg text-sm bg-marca-700 text-white">Guardar</button>
                </div>
            </div>
        </div>
    </div>
</template>
