// Global loader utilities (framework-agnostic) so we can trigger from Inertia events and Axios.
const listeners = new Set();
let pending = 0;

const notify = () => {
    const active = pending > 0;
    listeners.forEach((fn) => fn(active, pending));
};

export const startLoading = () => {
    pending += 1;
    notify();
};

export const stopLoading = () => {
    pending = Math.max(0, pending - 1);
    notify();
};

export const onLoadingChange = (callback) => {
    listeners.add(callback);
    // Immediately emit current state to new subscriber
    callback(pending > 0, pending);
    return () => listeners.delete(callback);
};
