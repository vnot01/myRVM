// resources/js/app.js
import '../css/app.css';
// import './bootstrap'; // Hanya jika Anda yakin isinya tidak konflik dan diperlukan

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js'; // <-- IMPORT DARI 'ziggy-js' (paket NPM)
import { Ziggy } from './ziggy'; // <-- Impor objek Ziggy dari file yang digenerate

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue')
        ),
    setup({ el, App, props, plugin }) {
        const vueApp = createApp({ render: () => h(App, props) });
        vueApp.use(plugin);
        // Gunakan objek Ziggy yang diimpor dari ./ziggy.js
        // ZiggyVue akan membuat fungsi route() tersedia secara global di komponen Vue
        vueApp.use(ZiggyVue, Ziggy);
        // vueApp.mixin({ methods: { route: window.route } }); // Hapus ini, tidak diperlukan lagi

        vueApp.mount(el);
        return vueApp;
    },
    progress: {
        color: '#4B5563',
    },
});