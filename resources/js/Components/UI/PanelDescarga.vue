<script setup>
/**
 * Panel de descarga/venta estilo DATAX (MAQUETA VISUAL — sin cobro real).
 * Muestra una vista previa + descripción y una tabla de formatos con precio,
 * enlace "DESCARGAR" (no disponible) y botón "Agregar al carrito". Al interactuar
 * invita a registrarse. La lógica de pago/descarga se conecta en una fase posterior.
 */
import { ref, computed } from 'vue';
import { colorOrg, logoOrg } from '../../lib/orgColors';

const props = defineProps({
    org: { type: Object, default: () => ({}) },
});

const color = computed(() => props.org?.color_primario || colorOrg(props.org?.id));
const logo = computed(() => logoOrg(props.org?.id) || logoOrg(props.org?.sigla));
const corte = computed(() => `Fecha de corte: 31-12-${props.org?.gestion_reciente || '2024'}`);

const formatos = [
    { clave: 'csv', nombre: 'CSV', icono: 'CSV', bg: '#0E7490', precio: '2,06' },
    { clave: 'xls_plana', nombre: 'XLS - Tabla plana', icono: 'XLS', bg: '#15803D', precio: '2,06' },
    { clave: 'xls_dinamica', nombre: 'XLS - Tabla dinámica', icono: 'XLS', bg: '#16A34A', precio: '2,06' },
    { clave: 'xls_informe', nombre: 'XLS - Informe', icono: 'XLS', bg: '#166534', precio: '2,48' },
    { clave: 'pdf', nombre: 'PDF - Reporte', icono: 'PDF', bg: '#B91C1C', precio: '2,48' },
];

const aviso = ref(false);
function interactuar() {
    aviso.value = true;
    setTimeout(() => (aviso.value = false), 4000);
}

const redes = [
    { nombre: 'Facebook', bg: '#1877F2', d: 'M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987H7.898v-2.89h2.54V9.797c0-2.508 1.493-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z' },
    { nombre: 'LinkedIn', bg: '#0A66C2', d: 'M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.13 1.45-2.13 2.94v5.67H9.35V9h3.42v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.34 7.43a2.06 2.06 0 110-4.13 2.06 2.06 0 010 4.13zM7.12 20.45H3.56V9h3.56v11.45zM22.22 0H1.77C.79 0 0 .77 0 1.72v20.56C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.72V1.72C24 .77 23.2 0 22.22 0z' },
    { nombre: 'WhatsApp', bg: '#25D366', d: 'M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.82 11.82 0 018.413 3.488 11.82 11.82 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 001.51 5.26l-.999 3.648 3.738-.957zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413z' },
];
</script>

<template>
    <div class="tarjeta overflow-hidden">
        <div class="grid grid-cols-1 lg:grid-cols-2">
            <!-- Vista previa + descripción -->
            <div class="p-6 border-b lg:border-b-0 lg:border-r border-gris-100">
                <div class="flex h-44 items-center justify-center rounded-xl"
                     :style="{ background: `linear-gradient(135deg, ${color} 0%, ${color}cc 100%)` }">
                    <div class="flex h-24 w-44 items-center justify-center rounded-lg bg-white/95 p-3 shadow-lg">
                        <img v-if="logo" :src="logo" :alt="org?.sigla" class="max-h-full max-w-full object-contain" />
                        <span v-else class="text-2xl font-bold" :style="{ color }">{{ org?.sigla }}</span>
                    </div>
                </div>
                <h3 class="mt-5 text-lg font-bold text-institucional-900">Datos listos para analizar</h3>
                <p class="mt-2 text-sm leading-relaxed text-institucional-500">
                    {{ org?.descripcion_corta || org?.descripcion || 'Descarga la base completa en el formato que prefieras: datos crudos, tablas dinámicas o un informe listo para presentar.' }}
                </p>
                <div class="mt-4 flex items-center gap-2">
                    <span class="text-xs text-gris-400">Compartir:</span>
                    <a v-for="r in redes" :key="r.nombre" href="#" @click.prevent="interactuar"
                       class="flex h-8 w-8 items-center justify-center rounded-full text-white transition hover:opacity-85"
                       :style="{ backgroundColor: r.bg }" :title="r.nombre" :aria-label="`Compartir en ${r.nombre}`">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path :d="r.d" /></svg>
                    </a>
                </div>
            </div>

            <!-- Tabla de formatos -->
            <div class="p-0">
                <div v-if="aviso" class="m-4 rounded-lg bg-rojo-50 px-4 py-2.5 text-sm font-medium text-rojo-700">
                    Regístrate para descargar o comprar estos datos. <a href="/acceder" class="underline">Crear cuenta →</a>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gris-200 text-left text-xs font-semibold uppercase tracking-wide text-institucional-500">
                            <th class="px-4 py-3">Opciones</th>
                            <th class="px-4 py-3">Descargar</th>
                            <th class="px-4 py-3 text-right">Precio</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gris-100">
                        <tr v-for="f in formatos" :key="f.clave" class="hover:bg-gris-50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5">
                                    <span class="rounded px-1.5 py-1 text-[10px] font-bold text-white" :style="{ backgroundColor: f.bg }">{{ f.icono }}</span>
                                    <span class="font-medium text-institucional-800">{{ f.nombre }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <button @click="interactuar" class="text-left">
                                    <span class="block text-xs font-semibold uppercase text-institucional-600 underline decoration-dotted hover:text-rojo-600">Descargar</span>
                                    <span class="block text-[11px] italic text-gris-400">Archivo no disponible</span>
                                </button>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="font-bold text-positivo">${{ f.precio }}</div>
                                <div class="mb-1.5 text-[10px] text-gris-400">{{ corte }}</div>
                                <button @click="interactuar" class="inline-flex items-center gap-1 rounded-md border border-gris-200 px-2.5 py-1 text-xs font-semibold text-institucional-700 transition hover:border-rojo-300 hover:bg-rojo-50 hover:text-rojo-600">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" /></svg>
                                    Agregar al carrito
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p class="px-4 py-3 text-[11px] text-gris-400">Precios ilustrativos. La compra y descarga se habilitan al iniciar sesión.</p>
            </div>
        </div>
    </div>
</template>
