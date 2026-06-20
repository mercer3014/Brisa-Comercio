<script setup>
/**
 * Modal de exportación — SOLO MAQUETA VISUAL (Fase 4). No conecta pago real.
 * Lista los formatos con su precio en créditos. El botón "Descargar" no descarga:
 * abre el aviso "Regístrate para descargar". La lógica de cobro/descarga es Fase 7.
 */
import { ref } from 'vue';

defineProps({
    abierto: { type: Boolean, default: false },
    titulo: { type: String, default: 'Descargar datos' },
});
const emit = defineEmits(['cerrar']);

const formatos = [
    { clave: 'csv',        nombre: 'CSV',                desc: 'Datos crudos separados por comas',           creditos: 1, icono: 'M3.75 9.75h16.5m-16.5 4.5h16.5' },
    { clave: 'xls_plana',  nombre: 'XLS · Tabla plana',  desc: 'Excel con una fila por registro',            creditos: 2, icono: 'M3.75 9.75h16.5m-16.5 4.5h16.5M9 4.5v15' },
    { clave: 'xls_dinamica', nombre: 'XLS · Tabla dinámica', desc: 'Excel con tabla pivote preconfigurada',  creditos: 3, icono: 'M3.75 6h16.5M3.75 12h16.5m-16.5 6h16.5' },
    { clave: 'xls_informe', nombre: 'XLS · Informe',     desc: 'Excel con gráficos y resumen ejecutivo',     creditos: 4, icono: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75z' },
    { clave: 'pdf',        nombre: 'PDF',                desc: 'Reporte imprimible con tablas y gráficos',   creditos: 4, icono: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25' },
];

const avisoRegistro = ref(false);

function intentarDescargar() {
    // Maqueta: en lugar de descargar, invita a registrarse.
    avisoRegistro.value = true;
}
function cerrarTodo() {
    avisoRegistro.value = false;
    emit('cerrar');
}
</script>

<template>
    <Teleport to="body">
        <div v-if="abierto" class="modal-overlay" @click.self="cerrarTodo">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-0 overflow-hidden">
                <!-- Cabecera -->
                <div class="px-6 py-5 border-b border-gris-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-institucional-900">{{ titulo }}</h3>
                        <p class="text-xs text-gris-500 mt-0.5">Elige un formato de exportación</p>
                    </div>
                    <button @click="cerrarTodo" class="p-2 rounded-lg text-gris-400 hover:bg-gris-100 hover:text-institucional-900 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- Aviso de registro (maqueta de gate de descarga) -->
                <div v-if="avisoRegistro" class="p-6 text-center">
                    <div class="w-14 h-14 mx-auto rounded-full bg-rojo-50 flex items-center justify-center mb-4">
                        <svg class="w-7 h-7 text-rojo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                    </div>
                    <h4 class="font-bold text-institucional-900 text-lg">Regístrate para descargar</h4>
                    <p class="text-sm text-gris-500 mt-2 max-w-sm mx-auto">
                        Crea una cuenta gratuita para obtener créditos y descargar los datos en el formato que elijas.
                        <span class="block mt-1 text-gris-400">(Maqueta — la descarga y el cobro se activan en una fase posterior.)</span>
                    </p>
                    <div class="flex gap-2.5 justify-center mt-6">
                        <button @click="avisoRegistro = false" class="btn btn-secundario">Volver</button>
                        <a href="/acceder" class="btn btn-primario">Crear cuenta</a>
                    </div>
                </div>

                <!-- Lista de formatos -->
                <div v-else class="p-4 space-y-2">
                    <button v-for="f in formatos" :key="f.clave"
                            @click="intentarDescargar"
                            class="w-full flex items-center gap-4 p-3.5 rounded-xl border border-gris-100 hover:border-rojo-200 hover:bg-gris-50 transition text-left group">
                        <span class="w-10 h-10 rounded-lg bg-institucional-50 text-institucional-700 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" :d="f.icono" /></svg>
                        </span>
                        <span class="flex-1 min-w-0">
                            <span class="block font-semibold text-institucional-900 text-sm">{{ f.nombre }}</span>
                            <span class="block text-xs text-gris-500 truncate">{{ f.desc }}</span>
                        </span>
                        <span class="flex items-center gap-2 shrink-0">
                            <span class="badge badge-info">{{ f.creditos }} créditos</span>
                            <span class="text-xs font-semibold text-rojo-600 inline-flex items-center gap-1">
                                Descargar
                                <svg class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                            </span>
                        </span>
                    </button>
                    <p class="text-[11px] text-gris-400 text-center pt-2">Los precios en créditos son ilustrativos.</p>
                </div>
            </div>
        </div>
    </Teleport>
</template>
