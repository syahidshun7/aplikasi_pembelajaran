import axios from 'axios';
import { startLoading, stopLoading } from './Utils/globalLoader';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Global loading hooks for manual Axios calls (skip Inertia requests to avoid double-counting)
window.axios.interceptors.request.use((config) => {
    if (!(config?.headers ?? {})['X-Inertia']) {
        startLoading();
    }
    return config;
});

window.axios.interceptors.response.use(
    (response) => {
        if (!(response.config?.headers ?? {})['X-Inertia']) {
            stopLoading();
        }
        return response;
    },
    (error) => {
        if (!(error.config?.headers ?? {})['X-Inertia']) {
            stopLoading();
        }
        return Promise.reject(error);
    },
);
