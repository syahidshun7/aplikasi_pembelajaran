import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echoInstance = null;

const resolveEchoConfig = () => {
    const key = import.meta.env.VITE_PUSHER_APP_KEY || import.meta.env.VITE_REVERB_APP_KEY || '';
    if (!key) {
        return null;
    }

    const host = import.meta.env.VITE_PUSHER_HOST
        || import.meta.env.VITE_REVERB_HOST
        || window.location.hostname;

    const scheme = import.meta.env.VITE_PUSHER_SCHEME
        || import.meta.env.VITE_REVERB_SCHEME
        || (window.location.protocol === 'https:' ? 'https' : 'http');

    const port = Number(
        import.meta.env.VITE_PUSHER_PORT
        || import.meta.env.VITE_REVERB_PORT
        || (scheme === 'https' ? 443 : 80),
    );

    return {
        broadcaster: 'pusher',
        key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
    };
};

export const initEcho = () => {
    if (echoInstance) {
        return echoInstance;
    }

    const config = resolveEchoConfig();
    if (!config) {
        return null;
    }

    window.Pusher = Pusher;
    echoInstance = new Echo(config);

    return echoInstance;
};
