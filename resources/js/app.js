import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import VueApexCharts from 'vue3-apexcharts';
import LayoutAdmin from './Layouts/LayoutAdmin.vue';
import LayoutPublico from './Layouts/LayoutPublico.vue';

const appName = import.meta.env.VITE_APP_NAME || 'ComexHub';

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
            .use(VueApexCharts)
            .mount(el);
    },
    progress: {
        color: '#2563eb',
    },
});
