<script setup>
/**
 * Gráficos específicos de FAOSTAT (organizacion_id = 4).
 * Subtipos: población, fertilizantes, subalimentación, cereales.
 * Aún sin Excel cargados → cada uno muestra estado vacío elegante. La estructura
 * de gráfico queda lista para cuando la API devuelva hay_datos = true.
 */
import ChartCard from '../UI/ChartCard.vue';
import EstadoVacio from '../UI/EstadoVacio.vue';
import LineChart from '../Charts/LineChart.vue';
import AreaChart from '../Charts/AreaChart.vue';
import BarChart from '../Charts/BarChart.vue';
import { useChartData } from '../Composables/useChartData.js';

const { data: poblacion, cargando: cPob } = useChartData('/api/v1/charts/faostat/poblacion');
const { data: fertil, cargando: cFer } = useChartData('/api/v1/charts/faostat/fertilizantes');
const { data: subal, cargando: cSub } = useChartData('/api/v1/charts/faostat/subalimentacion');
const { data: cereal, cargando: cCer } = useChartData('/api/v1/charts/faostat/cereales');

const hay = (d) => d?.meta?.hay_datos === true && (d?.categorias?.length ?? 0) > 0;
</script>

<template>
    <div class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <ChartCard titulo="Población rural vs urbana" fuente="FAOSTAT" :cargando="cPob">
                <LineChart v-if="hay(poblacion)" :series="poblacion.series" :categorias="poblacion.categorias" :height="320" :cargando="cPob" />
                <EstadoVacio v-else :mensaje="poblacion?.meta?.nota || 'Datos de población en proceso de carga.'" />
            </ChartCard>

            <ChartCard titulo="Consumo de fertilizantes (N, P, K)" fuente="FAOSTAT" :cargando="cFer">
                <AreaChart v-if="hay(fertil)" :series="fertil.series" :categorias="fertil.categorias" :apilada="true" :height="320" :cargando="cFer" />
                <EstadoVacio v-else :mensaje="fertil?.meta?.nota || 'Datos de fertilizantes en proceso de carga.'" />
            </ChartCard>

            <ChartCard titulo="Prevalencia de subalimentación" fuente="FAOSTAT" :cargando="cSub">
                <LineChart v-if="hay(subal)" :series="subal.series" :categorias="subal.categorias" :height="320" :cargando="cSub" />
                <EstadoVacio v-else :mensaje="subal?.meta?.nota || 'Datos de subalimentación en proceso de carga.'" />
            </ChartCard>

            <ChartCard titulo="Producción de cereales" fuente="FAOSTAT" :cargando="cCer">
                <BarChart v-if="hay(cereal)" :series="cereal.series" :categorias="cereal.categorias" :height="320" :cargando="cCer" />
                <EstadoVacio v-else :mensaje="cereal?.meta?.nota || 'Datos de cereales en proceso de carga.'" />
            </ChartCard>
        </div>
    </div>
</template>
