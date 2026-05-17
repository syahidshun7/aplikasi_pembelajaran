import '../css/app.css';
import './bootstrap';
import '../css/lobby-style.css';

import { createInertiaApp, usePage, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h, watch, onUnmounted } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import Swal from 'sweetalert2';
import GlobalLoadingBar from '@/Components/GlobalLoadingBar.vue';
import { startLoading, stopLoading } from '@/Utils/globalLoader';

const appName = import.meta.env.VITE_APP_NAME || 'DOOPTECH';
const PRELOAD_RECOVERY_KEY = 'vite-preload-recovered-once';

if (typeof window !== 'undefined') {
    window.addEventListener('vite:preloadError', (event) => {
        // Prevent hard crash when stale chunks are requested from mobile cache.
        if (event && typeof event.preventDefault === 'function') {
            event.preventDefault();
        }

        const payload = event && typeof event === 'object' && 'payload' in event
            ? event.payload
            : null;

        console.error('vite:preloadError', payload || event);

        try {
            const recovered = window.sessionStorage.getItem(PRELOAD_RECOVERY_KEY) === '1';
            if (!recovered) {
                window.sessionStorage.setItem(PRELOAD_RECOVERY_KEY, '1');
                window.location.reload();
            } else {
                window.sessionStorage.removeItem(PRELOAD_RECOVERY_KEY);
            }
        } catch (error) {
            console.error('preload recovery failed', error);
        }
    });

    window.addEventListener('unhandledrejection', (event) => {
        const reason = event?.reason;
        const message = String(reason?.message || reason || '').toLowerCase();
        const isPayloadUndefinedError = message.includes("cannot read properties of undefined (reading 'payload')")
            || message.includes('cannot read properties of undefined (reading "payload")');

        if (!isPayloadUndefinedError) return;

        console.error('Unhandled payload error', reason);

        if (event && typeof event.preventDefault === 'function') {
            event.preventDefault();
        }
    });
}

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
                // Inertia navigation hooks -> global loader
                const removeStart = router.on('start', () => startLoading());
                const removeFinish = router.on('finish', () => stopLoading());
                const removeError = router.on('error', () => stopLoading());
                const removeInvalid = router.on('invalid', () => stopLoading());

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

                watch(
                    () => page.props?.flash?.dailyQuest,
                    (payload) => {
                        if (!payload || typeof payload !== 'object') return;

                        const kind = String(payload.kind || 'progress');
                        const icon = kind === 'completed' || kind === 'claimed' ? 'success' : 'info';

                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon,
                            title: String(payload.title || 'DAILY QUEST'),
                            text: String(payload.text || ''),
                            showConfirmButton: false,
                            timer: kind === 'completed' ? 5200 : 4200,
                            background: '#101826',
                            color: '#d9f8ff',
                            iconColor: kind === 'completed' ? '#34d399' : '#22d3ee',
                            customClass: {
                                popup: 'border-2 border-cyan-900 font-mono text-[10px]',
                            },
                        });
                    },
                    { immediate: true, deep: true },
                );

                onUnmounted(() => {
                    removeStart?.();
                    removeFinish?.();
                    removeError?.();
                    removeInvalid?.();
                });

                return () => [
                    h(GlobalLoadingBar),
                    h(App, props),
                ];
            },
        };

        const app = createApp(Root);

        return app
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
