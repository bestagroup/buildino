import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import {
    createApp,
    h,
    type DefineComponent,
} from 'vue';
import '../css/management-inertia.css';

void createInertiaApp({
    title: (title) => title ? `${title} | Buildino` : 'Buildino',
    resolve: (name) => resolvePageComponent(
        `./pages/${name}.vue`,
        import.meta.glob('./pages/**/*.vue'),
    ) as Promise<DefineComponent>,
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#2563eb',
        showSpinner: false,
    },
});
