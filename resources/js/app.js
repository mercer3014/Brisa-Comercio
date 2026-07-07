import './bootstrap';
import '../css/app.css';

import { createApp, defineAsyncComponent, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import LayoutAdmin from './Layouts/LayoutAdmin.vue';
import LayoutPublico from './Layouts/LayoutPublico.vue';

// ApexCharts pesa ~250 KB (gzip) y solo lo usan algunas paginas con graficos.
// Se registra como componente global pero de carga diferida: el bundle solo
// se descarga cuando una pagina realmente renderiza un <apexchart>.
const ApexChartLazy = defineAsyncComponent(() => import('vue3-apexcharts').then((m) => m.default));

const appName = import.meta.env.VITE_APP_NAME || 'Geodata';

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    resolve: (name) => {
        const page = resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue')
        );
        // Layout por defecto segun la pagina: las del portal publico (Pages/Portal/*)
        // usan LayoutPublico; el resto, el LayoutAdmin del panel privado.
        // Las paginas pueden sobrescribir con `defineOptions({ layout: ... })` (ej. login = null).
        page.then((module) => {
            if (module.default.layout === undefined) {
                module.default.layout = name.startsWith('Portal/') ? LayoutPublico : LayoutAdmin;
            }
        });
        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .component('apexchart', ApexChartLazy)
            .mount(el);
    },
    progress: {
        color: '#2563eb',
    },
});
