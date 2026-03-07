import '../css/app.css';
import './bootstrap';

import { createInertiaApp, usePage } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h, watch } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import Swal from 'sweetalert2';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const Root = {
            setup() {
                const page = usePage();

                watch(
                    () => page.props?.flash?.message,
                    (message) => {
                        if (!message) return;
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: message,
                            showConfirmButton: false,
                            timer: 3000,
                            background: '#1a1c2c',
                            color: '#4ed4d4',
                            iconColor: '#4ed4d4',
                            customClass: {
                                popup: 'border-2 border-[#3d415f] font-mono text-[10px]',
                            },
                        });
                    },
                    { immediate: true },
                );

                return () => h(App, props);
            },
        };

        return createApp(Root)
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

