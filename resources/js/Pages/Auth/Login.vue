<script setup>
import { useForm, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// Esta pagina NO usa el layout de la app.
defineOptions({ layout: null });

const page = usePage();
const flash = computed(() => page.props.flash ?? {});

const form = useForm({
    nombre_usuario: '',
    contrasena: '',
});

function enviar() {
    form.post('/acceder', {
        onFinish: () => form.reset('contrasena'),
    });
}
</script>

<template>
    <Head title="Ingresar" />

    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-marca-800 to-marca-950 p-4">
        <div class="w-full max-w-md">
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-xl bg-white text-marca-800 text-2xl font-extrabold shadow-lg">C</div>
                <h1 class="text-white text-2xl font-bold mt-3">ComexHub</h1>
                <p class="text-marca-200 text-sm">Plataforma de comercio exterior</p>
            </div>

            <div class="bg-white rounded-2xl shadow-xl p-8">
                <h2 class="text-lg font-semibold text-slate-800 mb-1">Iniciar sesion</h2>
                <p class="text-slate-500 text-sm mb-6">Ingrese sus credenciales para continuar.</p>

                <div v-if="flash.error" class="mb-4 px-3 py-2 rounded bg-red-50 border border-red-200 text-red-700 text-sm">
                    {{ flash.error }}
                </div>
                <div v-if="flash.exito" class="mb-4 px-3 py-2 rounded bg-green-50 border border-green-200 text-green-700 text-sm">
                    {{ flash.exito }}
                </div>

                <form @submit.prevent="enviar" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Usuario</label>
                        <input
                            v-model="form.nombre_usuario"
                            type="text"
                            autofocus
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-marca-500 focus:border-marca-500"
                            :class="{ 'border-red-400': form.errors.nombre_usuario }"
                        />
                        <p v-if="form.errors.nombre_usuario" class="text-red-600 text-xs mt-1">{{ form.errors.nombre_usuario }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Contrasenia</label>
                        <input
                            v-model="form.contrasena"
                            type="password"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-marca-500 focus:border-marca-500"
                            :class="{ 'border-red-400': form.errors.contrasena }"
                        />
                        <p v-if="form.errors.contrasena" class="text-red-600 text-xs mt-1">{{ form.errors.contrasena }}</p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full bg-marca-700 hover:bg-marca-800 text-white font-medium py-2.5 rounded-lg transition disabled:opacity-60"
                    >
                        {{ form.processing ? 'Ingresando...' : 'Ingresar' }}
                    </button>
                </form>
            </div>

            <p class="text-center mt-6">
                <a href="/" class="text-marca-200 hover:text-white text-sm">&larr; Volver al portal publico</a>
            </p>
            <p class="text-center text-marca-300 text-xs mt-3">© {{ new Date().getFullYear() }} ComexHub · INE Bolivia</p>
        </div>
    </div>
</template>
