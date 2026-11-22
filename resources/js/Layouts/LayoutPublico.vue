<script setup>
/**
 * Layout del portal público con el estilo del mockup GEODATA (GEO-12):
 * barra institucional + barra vino/navy fija + pie institucional de 4 columnas.
 * Todo el portal vive dentro de .gd: ahí se aplican los estilos del mockup
 * (resources/css/portal-mockup.css) y se ajustan los tokens de Tailwind
 * (bordes rectos, paleta vino/navy) sin tocar el panel admin.
 */
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ToastFlash from '@/Components/ToastFlash.vue';

const page = usePage();
const usuario = computed(() => page.props.auth?.usuario ?? null);
const flash = computed(() => page.props.flash ?? {});

const navegacion = [
    { titulo: 'Inicio',                ruta: '/' },
    { titulo: 'Productos',             ruta: '/productos' },
    { titulo: 'Organizaciones',        ruta: '/organizaciones' },
    { titulo: 'Rankings y Comparador', ruta: '/rankings', extra: ['/comparador'] },
    { titulo: 'Mapa Comercial',        ruta: '/mapa-comercial' },
    { titulo: 'Indicadores',           ruta: '/indicadores' },
    { titulo: 'Línea de Tiempo',       ruta: '/linea-de-tiempo' },
    { titulo: 'Educación',             ruta: '/aprende', extra: ['/historias', '/actividad'] },
    { titulo: 'Acerca de',             ruta: '/acerca' },
];

const modulos = [
    { titulo: 'Inicio y panorama', ruta: '/' },
    { titulo: 'Productos y partidas arancelarias', ruta: '/productos' },
    { titulo: 'Vías de transporte', ruta: '/comercio-por-via' },
    { titulo: 'Organizaciones y descargas', ruta: '/organizaciones' },
    { titulo: 'Rankings y comparador', ruta: '/rankings' },
    { titulo: 'Mapa comercial', ruta: '/mapa-comercial' },
    { titulo: 'Indicadores', ruta: '/indicadores' },
    { titulo: 'Explorador de datos', ruta: '/explorar' },
    { titulo: 'Línea de tiempo', ruta: '/linea-de-tiempo' },
    { titulo: 'Educación: aprende, retos e historias', ruta: '/aprende' },
    { titulo: 'Acerca de', ruta: '/acerca' },
];

const rutaActual = computed(() => page.url.split('?')[0]);
const esInicio = computed(() => rutaActual.value === '/');
const menuMovil = ref(false);

function esActivo(item) {
    if (item.ruta === '/') return esInicio.value;
    return [item.ruta, ...(item.extra ?? [])].some((r) => rutaActual.value.startsWith(r));
}

const scrolleado = ref(false);
function alHacerScroll() {
    scrolleado.value = window.scrollY > 40;
}
const solida = computed(() => !esInicio.value || scrolleado.value);

onMounted(() => {
    alHacerScroll();
    window.addEventListener('scroll', alHacerScroll, { passive: true });
});
onUnmounted(() => window.removeEventListener('scroll', alHacerScroll));

const anio = new Date().getFullYear();
const cobertura = computed(() => page.props.cobertura ?? null);
</script>

<template>
    <div class="gd min-h-full flex flex-col">
        <header class="topbar" :class="{ solid: solida }">
            <div class="inst-bar">
                <div><b>IIESJOM · UAGRM - FCEE</b> | Instituto de Investigaciones Económicas y Sociales · Laboratorio <b>LICI</b></div>
            </div>
            <div class="nav-wrap">
                <Link class="logo" href="/">
                    <span class="logo-box">GD</span>
                    <span>Geodata</span>
                </Link>
                <nav class="nav-menu gd-nav-desktop" aria-label="Menú principal">
                    <Link v-for="item in navegacion" :key="item.ruta" :href="item.ruta"
                          class="nav-link" :class="{ active: esActivo(item) }">{{ item.titulo }}</Link>
                </nav>
                <div class="flex items-center gap-2">
                    <a v-if="usuario" href="/admin" class="btn-login" title="Panel administrativo">Ir al panel</a>
                    <Link v-else href="/acceder" class="btn-login" title="Panel administrativo">Acceder</Link>
                    <button class="btn-login gd-nav-toggle" aria-label="Abrir menú" @click="menuMovil = !menuMovil">☰</button>
                </div>
            </div>
            <nav v-show="menuMovil" class="gd-nav-movil" aria-label="Menú móvil">
                <Link v-for="item in navegacion" :key="item.ruta" :href="item.ruta"
                      class="nav-link" :class="{ active: esActivo(item) }" @click="menuMovil = false">{{ item.titulo }}</Link>
            </nav>
        </header>

        <!-- La portada tiene hero a pantalla completa: no reserva espacio bajo la barra -->
        <div v-if="!esInicio" class="gd-espaciador" aria-hidden="true"></div>

        <ToastFlash :mensaje="flash.exito" tipo="exito" />
        <ToastFlash :mensaje="flash.error" tipo="error" />

        <main class="flex-1">
            <slot />
        </main>

        <footer class="footer">
            <div class="ft-cols">
                <div>
                    <h4>GEODATA · UAGRM - FCEE</h4>
                    <p>Centralizador de Base de Datos del Comercio Exterior Boliviano en una Plataforma. Desarrollado por el
                        equipo de Base de Datos del <b>IIESJOM</b> con el apoyo del <b>LICI</b>.</p>
                    <p v-if="cobertura" style="margin-top:10px;font-size:0.76rem;color:#e7b9c1">
                        INE · Exportaciones {{ cobertura.exp?.etiqueta }} · Importaciones {{ cobertura.imp?.etiqueta }}
                    </p>
                    <p style="font-size:0.74rem;color:#e7b9c1">Cifras calculadas en vivo desde la base de datos del sistema.</p>
                </div>
                <div>
                    <h4>Módulos de la Plataforma</h4>
                    <Link v-for="m in modulos" :key="m.ruta" :href="m.ruta">{{ m.titulo }}</Link>
                </div>
                <div>
                    <h4>Fuentes Oficiales</h4>
                    <a href="https://www.ine.gob.bo" target="_blank" rel="noopener">INE · Instituto Nacional de Estadística</a>
                    <a href="https://www.fao.org/faostat" target="_blank" rel="noopener">FAOSTAT · Naciones Unidas</a>
                    <a href="https://www.aladi.org" target="_blank" rel="noopener">ALADI · Asociación Latinoamericana</a>
                    <a href="https://www.mercosur.int" target="_blank" rel="noopener">MERCOSUR · Mercado Común del Sur</a>
                    <p style="margin-top:10px;font-size:0.72rem;color:#e7b9c1">Nomenclatura: NANDINA / Sistema Armonizado.
                        Países homologados a ISO 3166 (alfa-2, alfa-3 y M49).</p>
                </div>
                <div>
                    <h4>Contacto Institucional</h4>
                    <a href="mailto:iiesjom@uagrm.edu.bo">iiesjom@uagrm.edu.bo</a>
                    <a href="https://lici-uagrm.com/" target="_blank" rel="noopener">https://lici-uagrm.com/</a>
                    <p style="margin-top:8px;font-size:0.78rem;color:#e7b9c1">Facultad de Ciencias Económicas y Empresariales ·
                        Santa Cruz de la Sierra, Bolivia.</p>
                    <a :href="usuario ? '/admin' : '/acceder'" style="margin-top:8px">Panel administrativo →</a>
                </div>
            </div>
            <div class="ft-bottom">© {{ anio }} IIESJOM · LICI · UAGRM. Uso académico con cita de las fuentes oficiales.</div>
        </footer>
    </div>
</template>
