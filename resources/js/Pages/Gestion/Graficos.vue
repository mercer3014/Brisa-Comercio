<script setup>
// Pestaña "Explorador de gráficos" (diseño del mockup) con datos REALES:
// fuente, flujo, variable, agrupación, rango de años, top N, tipo de gráfico y
// filtro de texto. Consulta /admin/gestion/graficos/consultar.
import { ref, computed, watch, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import CabeceraGestion from '@/Components/Gestion/CabeceraGestion.vue';
import BarChart from '@/Components/Charts/BarChart.vue';
import LineChart from '@/Components/Charts/LineChart.vue';
import DonutChart from '@/Components/Charts/DonutChart.vue';

const props = defineProps({
    fuentes: Array,
    gestiones: Array,
    zonas: Array,
});

const fuente = ref(props.fuentes[0]?.clave ?? 'INE');
const fuenteDef = computed(() => props.fuentes.find((f) => f.clave === fuente.value));
const agrupar = ref('gestion');
const flujo = ref('ambos'); // exp | imp | ambos | saldo
const variable = ref('usd');
const desde = ref(props.gestiones.length ? props.gestiones[Math.max(0, props.gestiones.length - 10)] : '');
const hasta = ref(props.gestiones.length ? props.gestiones[props.gestiones.length - 1] : '');
const top = ref(15);
const tipo = ref('columnas'); // lineas | columnas | barras | anillo
const filtro = ref('');
const zonaId = ref('');

const cargando = ref(false);
const error = ref('');
const filas = ref([]);
const unidad = ref('USD');

watch(fuente, () => {
    if (!fuenteDef.value.agrupar.some((a) => a.id === agrupar.value)) agrupar.value = 'gestion';
    if (!fuenteDef.value.con_peso) variable.value = 'usd';
});

let t = null;
watch([fuente, agrupar, variable, desde, hasta, top, zonaId], () => consultar());
watch(filtro, () => { clearTimeout(t); t = setTimeout(consultar, 400); });

async function consultar() {
    cargando.value = true;
    error.value = '';
    try {
        const { data } = await axios.post('/admin/gestion/graficos/consultar', {
            fuente: fuente.value, agrupar: agrupar.value, variable: variable.value,
            desde: desde.value || null, hasta: hasta.value || null, top: top.value,
            zona_id: fuente.value.startsWith('MERCOSUR') ? (zonaId.value || null) : null,
            filtro: filtro.value || null,
        });
        filas.value = data.filas;
        unidad.value = data.unidad;
    } catch (e) {
        error.value = e.response?.data?.message || 'No se pudo consultar.';
        filas.value = [];
    } finally {
        cargando.value = false;
    }
}
onMounted(consultar);

const categorias = computed(() => filas.value.map((f) => f.etiqueta));
const series = computed(() => {
    const s = [];
    if (flujo.value === 'exp' || flujo.value === 'ambos') s.push({ name: 'Exportaciones', data: filas.value.map((f) => Math.round(f.exportaciones)) });
    if (flujo.value === 'imp' || flujo.value === 'ambos') s.push({ name: 'Importaciones', data: filas.value.map((f) => Math.round(f.importaciones)) });
    if (flujo.value === 'saldo') s.push({ name: 'Saldo', data: filas.value.map((f) => Math.round(f.saldo)) });
    return s;
});
const valorDe = (f) => (flujo.value === 'imp' ? f.importaciones : flujo.value === 'saldo' ? f.saldo : flujo.value === 'exp' ? f.exportaciones : f.exportaciones + f.importaciones);
const donut = computed(() => filas.value.map((f) => Math.max(0, Math.round(valorDe(f)))));

const nf = new Intl.NumberFormat('es-BO', { maximumFractionDigits: 0 });
const total = computed(() => filas.value.reduce((a, f) => a + valorDe(f), 0));
const lider = computed(() => [...filas.value].sort((a, b) => valorDe(b) - valorDe(a))[0] ?? null);
const conc5 = computed(() => {
    const vals = filas.value.map(valorDe).filter((v) => v > 0).sort((a, b) => b - a);
    const sum = vals.reduce((a, v) => a + v, 0);
    return sum > 0 ? (vals.slice(0, 5).reduce((a, v) => a + v, 0) / sum) * 100 : 0;
});
const opcionesGrafico = computed(() => ({
    colors: flujo.value === 'saldo' ? ['#12203d'] : flujo.value === 'imp' ? ['#12203d'] : ['#5e1224', '#12203d'],
    yaxis: { labels: { formatter: (v) => nf.format(v) } },
    tooltip: { y: { formatter: (v) => `${nf.format(v)} ${unidad.value}` } },
    dataLabels: { enabled: false },
}));
const avisoZonas = computed(() => fuente.value.startsWith('MERCOSUR') && !zonaId.value && agrupar.value !== 'zona');

function descargarCsv() {
    const enc = ['Etiqueta', `Exportaciones (${unidad.value})`, `Importaciones (${unidad.value})`, `Saldo (${unidad.value})`];
    const lineas = [enc, ...filas.value.map((f) => [f.etiqueta, f.exportaciones, f.importaciones, f.saldo])]
        .map((r) => r.map((v) => `"${String(v).replace(/"/g, '""')}"`).join(';'));
    const blob = new Blob(['\ufeff' + lineas.join('\n')], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `geodata_${fuente.value}_${agrupar.value}.csv`;
    a.click();
    URL.revokeObjectURL(a.href);
}
</script>

<template>
    <Head title="Gestión de datos · Explorador de gráficos" />

    <div class="max-w-7xl mx-auto">
        <CabeceraGestion activa="graficos" subtitulo="Explora los datos cargados en la base: elige la fuente, el flujo y cómo agrupar. Las cifras son las reales de PostgreSQL." />

        <section class="bg-white border border-gris-200 rounded-md shadow-sm p-5 mb-4">
            <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-8 gap-3 text-sm">
                <label class="col-span-2 md:col-span-1 xl:col-span-2">
                    <span class="block text-[10px] font-bold tracking-widest uppercase text-[#5e1224] mb-1">Fuente</span>
                    <select v-model="fuente" class="w-full border border-gris-200 rounded-sm px-2 py-1.5">
                        <option v-for="f in fuentes" :key="f.clave" :value="f.clave">{{ f.titulo }}</option>
                    </select>
                </label>
                <label>
                    <span class="block text-[10px] font-bold tracking-widest uppercase text-[#5e1224] mb-1">Flujo</span>
                    <select v-model="flujo" class="w-full border border-gris-200 rounded-sm px-2 py-1.5">
                        <option value="ambos">Ambos</option><option value="exp">Exportaciones</option>
                        <option value="imp">Importaciones</option><option value="saldo">Saldo</option>
                    </select>
                </label>
                <label>
                    <span class="block text-[10px] font-bold tracking-widest uppercase text-[#5e1224] mb-1">Variable</span>
                    <select v-model="variable" class="w-full border border-gris-200 rounded-sm px-2 py-1.5">
                        <option value="usd">Valor (USD)</option>
                        <option value="kg" :disabled="!fuenteDef?.con_peso">Peso (kg)</option>
                    </select>
                </label>
                <label>
                    <span class="block text-[10px] font-bold tracking-widest uppercase text-[#5e1224] mb-1">Agrupar por</span>
                    <select v-model="agrupar" class="w-full border border-gris-200 rounded-sm px-2 py-1.5">
                        <option v-for="a in fuenteDef?.agrupar ?? []" :key="a.id" :value="a.id">{{ a.label }}</option>
                    </select>
                </label>
                <label>
                    <span class="block text-[10px] font-bold tracking-widest uppercase text-[#5e1224] mb-1">Desde</span>
                    <select v-model="desde" class="w-full border border-gris-200 rounded-sm px-2 py-1.5">
                        <option value="">—</option><option v-for="g in gestiones" :key="g" :value="g">{{ g }}</option>
                    </select>
                </label>
                <label>
                    <span class="block text-[10px] font-bold tracking-widest uppercase text-[#5e1224] mb-1">Hasta</span>
                    <select v-model="hasta" class="w-full border border-gris-200 rounded-sm px-2 py-1.5">
                        <option value="">—</option><option v-for="g in gestiones" :key="g" :value="g">{{ g }}</option>
                    </select>
                </label>
                <label>
                    <span class="block text-[10px] font-bold tracking-widest uppercase text-[#5e1224] mb-1">Top N</span>
                    <select v-model.number="top" class="w-full border border-gris-200 rounded-sm px-2 py-1.5">
                        <option v-for="n in [5, 10, 15, 20, 30, 50]" :key="n" :value="n">{{ n }}</option>
                    </select>
                </label>
                <label v-if="fuente.startsWith('MERCOSUR')" class="col-span-2">
                    <span class="block text-[10px] font-bold tracking-widest uppercase text-[#5e1224] mb-1">Zona</span>
                    <select v-model="zonaId" class="w-full border border-gris-200 rounded-sm px-2 py-1.5">
                        <option value="">Todas</option><option v-for="z in zonas" :key="z.id" :value="z.id">{{ z.label }}</option>
                    </select>
                </label>
                <label class="col-span-2">
                    <span class="block text-[10px] font-bold tracking-widest uppercase text-[#5e1224] mb-1">Filtro de texto</span>
                    <input v-model="filtro" type="search" placeholder="ej. café, Brasil, 26…" class="w-full border border-gris-200 rounded-sm px-2 py-1.5" />
                </label>
                <label class="col-span-2">
                    <span class="block text-[10px] font-bold tracking-widest uppercase text-[#5e1224] mb-1">Gráfico</span>
                    <div class="flex gap-1">
                        <button v-for="g in [['columnas','Columnas'],['barras','Barras'],['lineas','Líneas'],['anillo','Anillo']]" :key="g[0]"
                                class="flex-1 px-2 py-1.5 text-xs font-bold rounded-sm border"
                                :class="tipo === g[0] ? 'bg-[#12203d] text-white border-[#12203d]' : 'border-gris-200 hover:bg-gris-50'"
                                @click="tipo = g[0]">{{ g[1] }}</button>
                    </div>
                </label>
            </div>
            <p v-if="avisoZonas" class="mt-3 text-[11px] text-amber-700">MERCOSUR publica una serie por zona y las zonas se solapan (p.ej. «MERCOSUR» y «Extrazona»): elija una zona para no sumar dos veces el mismo comercio.</p>
        </section>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
            <div class="bg-white border border-gris-200 rounded-md p-4">
                <div class="text-[10px] font-bold tracking-widest uppercase text-[#5e1224]">Total ({{ unidad }})</div>
                <div class="text-2xl font-extrabold text-[#12203d]">{{ nf.format(total) }}</div>
            </div>
            <div class="bg-white border border-gris-200 rounded-md p-4">
                <div class="text-[10px] font-bold tracking-widest uppercase text-[#5e1224]">Líder</div>
                <div class="text-sm font-bold text-[#12203d] truncate">{{ lider?.etiqueta ?? '—' }}</div>
                <div class="text-xs text-gris-500">{{ lider ? nf.format(valorDe(lider)) + ' ' + unidad : '' }}</div>
            </div>
            <div class="bg-white border border-gris-200 rounded-md p-4">
                <div class="text-[10px] font-bold tracking-widest uppercase text-[#5e1224]">Concentración de las 5 primeras</div>
                <div class="text-2xl font-extrabold text-[#12203d]">{{ conc5.toLocaleString('es-BO', { maximumFractionDigits: 1 }) }} %</div>
            </div>
        </div>

        <section class="bg-white border border-gris-200 rounded-md shadow-sm p-5">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-base font-bold text-[#12203d]">{{ fuenteDef?.titulo }} · {{ fuenteDef?.agrupar.find(a => a.id === agrupar)?.label }}</h3>
                <button class="px-3 py-1.5 text-xs font-bold border border-gris-200 bg-gris-50 hover:bg-white rounded-sm" :disabled="!filas.length" @click="descargarCsv">Descargar (CSV para Excel)</button>
            </div>
            <div v-if="error" class="text-sm text-red-600 mb-2">{{ error }}</div>
            <DonutChart v-if="tipo === 'anillo'" :series="donut" :categorias="categorias" :cargando="cargando" :height="380" />
            <LineChart v-else-if="tipo === 'lineas'" :series="series" :categorias="categorias" :cargando="cargando" :height="380" :opciones="opcionesGrafico" />
            <BarChart v-else :series="series" :categorias="categorias" :horizontal="tipo === 'barras'" :cargando="cargando" :height="tipo === 'barras' ? Math.max(380, filas.length * 26) : 380" :opciones="opcionesGrafico" />

            <div class="mt-4 overflow-x-auto max-h-96">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase text-gris-500 sticky top-0 bg-white">
                        <tr><th class="text-left py-1.5">Etiqueta</th><th class="text-right">Exportaciones</th><th class="text-right">Importaciones</th><th class="text-right">Saldo</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gris-100">
                        <tr v-for="f in filas" :key="f.etiqueta">
                            <td class="py-1">{{ f.etiqueta }}</td>
                            <td class="text-right">{{ nf.format(f.exportaciones) }}</td>
                            <td class="text-right">{{ nf.format(f.importaciones) }}</td>
                            <td class="text-right" :class="f.saldo >= 0 ? 'text-positivo' : 'text-negativo'">{{ nf.format(f.saldo) }}</td>
                        </tr>
                        <tr v-if="!filas.length && !cargando"><td colspan="4" class="py-6 text-center text-gris-400">Sin datos para estos filtros.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
