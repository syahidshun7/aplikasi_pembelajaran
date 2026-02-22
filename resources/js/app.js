import '../css/app.css';
import './bootstrap';

import { createInertiaApp ,usePage} from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, watch ,h} from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import Swal from 'sweetalert2';

const page = usePage();

// Pantau setiap ada pesan dari controller
watch(() => page.props?.flash?.message, (message) => {
    if (message) {
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
                popup: 'border-2 border-[#3d415f] font-mono text-[10px]'
            }
        });
    }
}, { immediate: true });

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});


