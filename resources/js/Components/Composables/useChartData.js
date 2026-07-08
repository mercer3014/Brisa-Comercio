import { ref, shallowRef, watch, unref, onMounted } from 'vue';
import axios from 'axios';

/**
 * Consume los endpoints /api/v1/charts/* (y cualquier endpoint de la API Fase 3)
 * con estados de carga y error listos para skeletons.
 *
 * Uso:
 *   const { data, cargando, error, recargar } = useChartData('/api/v1/charts/top-países', () => ({ gestión: 2024, limit: 10 }));
 *
 * @param {string|import('vue').Ref<string>} url        Endpoint (absoluto o relativo).
 * @param {Function} [paramsFn]  Función reactiva que devuelve el objeto de query params.
 * @param {Object}  [opts]
 * @param {boolean} [opts.inmediato=true]  Cargar al montar.
 * @param {*}       [opts.inicial=null]     Valor inicial de `data`.
 */
export function useChartData(url, paramsFn = () => ({}), opts = {}) {
    const { inmediato = true, inicial = null } = opts;

    const data = shallowRef(inicial);
    const cargando = ref(false);
    const error = ref(null);
    let peticionId = 0;

    async function recargar() {
        const id = ++peticionId;
        cargando.value = true;
        error.value = null;
        try {
            const { data: payload } = await axios.get(unref(url), {
                params: paramsFn() ?? {},
                headers: { Accept: 'application/json' },
            });
            // Evita condiciones de carrera: solo aplica la última petición.
            if (id === peticionId) {
                data.value = payload;
            }
        } catch (e) {
            if (id === peticionId) {
                error.value = e?.response?.data?.message || e.message || 'Error al cargar los datos';
                data.value = inicial;
            }
        } finally {
            if (id === peticionId) {
                cargando.value = false;
            }
        }
    }

    // Recarga automática cuando cambian los params (si paramsFn es reactiva).
    watch(paramsFn, recargar, { deep: true });

    if (inmediato) {
        onMounted(recargar);
    }

    return { data, cargando, error, recargar };
}

/** Helper directo (sin reactividad) para cargas puntuales. */
export async function fetchChart(url, params = {}) {
    const { data } = await axios.get(url, { params, headers: { Accept: 'application/json' } });
    return data;
}
