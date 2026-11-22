<script setup>
/**
 * Barras agrupadas por año en SVG propio (sin librerías), con soporte de
 * valores negativos (saldo). null = "sin datos": se dibuja un recuadro
 * punteado, nunca una barra en cero.
 */
import { computed } from 'vue';
import { fmtM } from '@/lib/gd';

const props = defineProps({
    series: { type: Array, default: () => [] }, // [{clave, nombre, color}]
    puntos: { type: Array, default: () => [] }, // [{etiqueta, resaltar, [clave]: valor|null}]
    alto: { type: Number, default: 230 },
});

const ancho = computed(() => Math.max(1, props.series.length) * 13 + 8);
const W = computed(() => props.puntos.length * ancho.value + 40);
const valores = computed(() => props.puntos.flatMap((p) => props.series.map((s) => p[s.clave])).filter((v) => v !== null && v !== undefined));
const max = computed(() => Math.max(0, ...valores.value));
const min = computed(() => Math.min(0, ...valores.value));
const rango = computed(() => (max.value - min.value) || 1);
const top = 14;
const util = computed(() => props.alto - top - 44);
const y = (v) => top + ((max.value - v) / rango.value) * util.value;
const y0 = computed(() => y(0));
const rotar = computed(() => props.puntos.length > 10);
</script>

<template>
    <div class="edu-serie">
        <div class="edu-leyenda">
            <span v-for="s in series" :key="s.clave"><i :style="{ background: s.color }"></i>{{ s.nombre }}</span>
            <span class="vacio"><i></i>Sin datos cargados</span>
        </div>
        <svg :viewBox="`0 0 ${W} ${alto}`" style="width:100%;height:auto" role="img" :aria-label="series.map((s) => s.nombre).join(', ')">
            <template v-for="(p, i) in puntos" :key="p.etiqueta">
                <rect v-if="p.resaltar" :x="20 + i * ancho - 2" :y="top - 6" :width="ancho" :height="util + 10" fill="#fbf1f3" />
                <template v-for="(s, j) in series" :key="s.clave">
                    <rect v-if="p[s.clave] !== null && p[s.clave] !== undefined"
                          :x="20 + i * ancho + j * 13" :y="Math.min(y(p[s.clave]), y0)" width="11"
                          :height="Math.max(1, Math.abs(y(p[s.clave]) - y0))" :fill="s.color" :opacity="p.resaltar || !puntos.some((x) => x.resaltar) ? 1 : 0.55">
                        <title>{{ p.etiqueta }} · {{ s.nombre }}: {{ fmtM(p[s.clave]) }}</title>
                    </rect>
                    <rect v-else :x="20 + i * ancho + j * 13" :y="y0 - 9" width="11" height="9" fill="none" stroke="#cbd5e1" stroke-dasharray="2 2">
                        <title>{{ p.etiqueta }} · {{ s.nombre }}: sin datos cargados</title>
                    </rect>
                </template>
                <text :x="20 + i * ancho + (ancho - 8) / 2" :y="top + util + 16" :text-anchor="rotar ? 'end' : 'middle'" font-size="9.5"
                      :font-weight="p.resaltar ? 800 : 600" :fill="p.resaltar ? '#5e1224' : '#64748b'"
                      :transform="rotar ? `rotate(-45 ${20 + i * ancho + (ancho - 8) / 2} ${top + util + 16})` : ''">{{ p.etiqueta }}</text>
            </template>
            <line x1="14" :y1="y0 + 0.5" :x2="W - 10" :y2="y0 + 0.5" stroke="#94a3b8" />
            <text x="16" :y="top - 2" font-size="9" fill="#94a3b8">{{ fmtM(max) }}</text>
            <text v-if="min < 0" x="16" :y="top + util + 4" font-size="9" fill="#b91c1c">{{ fmtM(min) }}</text>
        </svg>
    </div>
</template>
