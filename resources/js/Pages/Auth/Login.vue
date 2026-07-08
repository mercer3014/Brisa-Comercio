<script setup>
import { useForm, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// Esta página NO usa el layout de la app.
defineOptions({ layout: null });

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const nombreApp = computed(() => page.props.app?.nombre ?? 'Geodata');

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

    <div class="min-h-screen flex bg-white">
        <!-- ===== Panel lateral con imagen (puerto) ===== -->
        <div class="hidden lg:flex lg:w-[48%] relative flex-col justify-between p-14 overflow-hidden text-white">
            <div class="absolute inset-0 bg-cover bg-center"
                 style="background-image:url('https://images.unsplash.com/photo-1605745341112-85968b19335b?auto=format&fit=crop&w=1200&q=80')"></div>
            <!-- Velo navy ligero: nitido arriba, legible abajo para el texto -->
            <div class="absolute inset-0 bg-gradient-to-t from-institucional-950/95 via-institucional-950/45 to-institucional-900/30"></div>
            <!-- Orbe decorativo -->
            <div class="absolute -bottom-32 -right-32 w-96 h-96 rounded-full bg-rojo-600/20 blur-3xl pointer-events-none"></div>

            <!-- Marca -->
            <div class="relative flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-rojo-600 flex items-center justify-center shadow-lg">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18"/>
                    </svg>
                </div>
                <span class="font-bold text-xl tracking-tight">{{ nombreApp }}</span>
            </div>

            <!-- Mensaje -->
            <div class="relative max-w-md">
                <p class="pildora bg-white/10 text-white ring-1 ring-white/20 mb-6 backdrop-blur-sm w-fit">
                    <span class="w-2 h-2 rounded-full bg-rojo-400 animate-pulse"></span> Panel de administración
                </p>
                <h1 class="titular-editorial text-[2.6rem] leading-tight">
                    El comercio exterior de Bolivia, <span class="subrayado-rojo">gestionado con rigor</span>.
                </h1>
                <p class="text-institucional-300 mt-5 leading-relaxed text-[15px]">
                    Carga, procesa y publica los datos oficiales del INE desde un solo lugar.
                </p>
            </div>

            <!-- Copyright -->
            <div class="relative text-xs text-institucional-400">© {{ new Date().getFullYear() }} Geodata</div>
        </div>

        <!-- ===== Formulario ===== -->
        <div class="flex-1 flex items-center justify-center px-6 py-10 bg-gris-50">
            <div class="w-full max-w-[380px]">
                <!-- Marca (movil) -->
                <div class="lg:hidden text-center mb-8">
                    <div class="w-14 h-14 rounded-2xl bg-rojo-600 flex items-center justify-center mx-auto shadow-lg">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18"/>
                        </svg>
                    </div>
                    <h1 class="text-institucional-900 text-2xl font-bold mt-4">{{ nombreApp }}</h1>
                </div>

                <!-- Card del formulario -->
                <div class="bg-white rounded-2xl shadow-tarjeta border border-gris-200 p-8">
                    <h2 class="text-2xl font-bold text-institucional-900 tracking-tight">Iniciar sesión</h2>
                    <p class="text-gris-500 text-sm mt-1.5 mb-7">Ingrese sus credenciales para acceder al panel.</p>

                <div v-if="flash.error" class="mb-4 px-3 py-2.5 rounded-lg bg-negativo-suave border border-negativo/30 text-negativo text-sm font-medium">
                    {{ flash.error }}
                </div>
                <div v-if="flash.exito" class="mb-4 px-3 py-2.5 rounded-lg bg-positivo-suave border border-positivo/30 text-positivo text-sm font-medium">
                    {{ flash.exito }}
                </div>

                <form @submit.prevent="enviar" class="space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-institucional-800 mb-1.5">Usuario</label>
                        <input
                            v-model="form.nombre_usuario"
                            type="text"
                            autofocus
                            class="campo"
                            :class="{ 'campo-error': form.errors.nombre_usuario }"
                        />
                        <p v-if="form.errors.nombre_usuario" class="text-negativo text-xs mt-1">{{ form.errors.nombre_usuario }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-institucional-800 mb-1.5">Contrasenia</label>
                        <input
                            v-model="form.contrasena"
                            type="password"
                            class="campo"
                            :class="{ 'campo-error': form.errors.contrasena }"
                        />
                        <p v-if="form.errors.contrasena" class="text-negativo text-xs mt-1">{{ form.errors.contrasena }}</p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="btn btn-primario w-full"
                    >
                        {{ form.processing ? 'Ingresando...' : 'Ingresar' }}
                    </button>
                </form>

                    <p class="text-center mt-6 pt-6 border-t border-gris-100">
                        <a href="/" class="inline-flex items-center gap-1.5 text-gris-400 hover:text-rojo-600 text-sm font-medium transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                            Volver al portal
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
