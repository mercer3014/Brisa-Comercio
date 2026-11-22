<script setup>
/** Pinta un "bloque" de gráfico armado por DatosEducativos (lecciones e historias). */
import GdSerieBarras from '@/Components/Gd/GdSerieBarras.vue';
import GdBarras from '@/Components/Gd/GdBarras.vue';
import GdDona from '@/Components/Gd/GdDona.vue';
import GdSinDatos from '@/Components/Gd/GdSinDatos.vue';
import { fmtM, fmtPct, fmtNum } from '@/lib/gd';

defineProps({ bloque: { type: Object, required: true } });

const COLORES = ['#5e1224', '#3b7a8c', '#f59e0b', '#10b981', '#7c5dfa', '#94a3b8'];
function kpi(it) {
    if (it.valor === null || it.valor === undefined) return null;
    if (it.formato === 'pct') return fmtPct(it.valor);
    if (it.formato === 'hhi') return fmtNum(it.valor, 3);
    return fmtM(it.valor, it.valor < 0);
}
function celda(c) {
    if (c && typeof c === 'object') {
        if ('usd' in c) return fmtM(c.usd);
        if ('pct' in c) return fmtPct(c.pct, true);
    }
    return c ?? '—';
}
</script>

<template>
    <div class="edu-bloque">
        <h4 v-if="bloque.titulo && bloque.tipo !== 'sin_datos'" class="edu-bloque-t">{{ bloque.titulo }}</h4>

        <GdSerieBarras v-if="bloque.tipo === 'barras_serie'" :series="bloque.series" :puntos="bloque.puntos" />

        <div v-else-if="bloque.tipo === 'kpis'" class="edu-kpis">
            <div v-for="it in bloque.items" :key="it.nombre" class="gd-kpi-mini" :style="{ borderTop: `3px solid ${it.color || '#12203d'}` }">
                <small>{{ it.nombre }}</small>
                <strong v-if="kpi(it) !== null" :class="{ neg: it.formato === 'usd' && it.valor < 0 }">{{ kpi(it) }}</strong>
                <span v-else class="gd-tag-sindatos">Sin datos</span>
            </div>
        </div>

        <div v-else-if="bloque.tipo === 'doble_lista'" class="edu-doble">
            <div v-for="lado in [bloque.izq, bloque.der]" :key="lado.titulo">
                <h5>{{ lado.titulo }}</h5>
                <GdBarras v-if="lado.items?.length" :items="lado.items" :color="lado.color" />
                <GdSinDatos v-else :titulo="lado.vacio || 'Sin datos'" />
            </div>
        </div>

        <GdDona v-else-if="bloque.tipo === 'dona' && bloque.partes?.length" :partes="bloque.partes" :colores="COLORES" />
        <GdSinDatos v-else-if="bloque.tipo === 'dona'" titulo="Sin datos para esta gestión" />

        <GdBarras v-else-if="bloque.tipo === 'barras'" :items="bloque.items || []" :color="bloque.color" />

        <div v-else-if="bloque.tipo === 'tabla'" style="overflow-x:auto">
            <table class="data-table edu-tabla">
                <thead><tr><th v-for="c in bloque.columnas" :key="c">{{ c }}</th></tr></thead>
                <tbody>
                    <tr v-for="(f, i) in bloque.filas" :key="i">
                        <td v-for="(c, j) in f" :key="j" :class="{ 'gd-num': j > 0 }">{{ celda(c) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-else-if="bloque.tipo === 'aviso'" class="edu-aviso">⚠ {{ bloque.texto }}</p>

        <GdSinDatos v-else :titulo="bloque.titulo || 'Sin datos'" :mensaje="bloque.mensaje || ''" />

        <p v-if="bloque.nota" class="gd-mini-nota" style="margin-top:6px">{{ bloque.nota }}</p>
    </div>
</template>
