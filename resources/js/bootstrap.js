import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;
window.Pusher = Pusher;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

function readXsrfCookie() {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : null;
}

window.axios.interceptors.request.use((config) => {
    const xsrf = readXsrfCookie();
    if (xsrf) {
        config.headers['X-XSRF-TOKEN'] = xsrf;
    }
    return config;
});

window.getCsrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

/**
 * Wait for Vite module Echo init (inline Blade scripts often run first).
 */
window.whenEchoReady = function whenEchoReady(callback, timeoutMs = 15000) {
    if (typeof callback !== 'function') {
        return;
    }

    if (window.Echo) {
        callback(window.Echo);
        return;
    }

    const started = Date.now();
    const onReady = () => {
        window.removeEventListener('echo:ready', onReady);
        if (window.Echo) {
            callback(window.Echo);
        }
    };

    window.addEventListener('echo:ready', onReady);

    const poll = window.setInterval(() => {
        if (window.Echo) {
            window.clearInterval(poll);
            window.removeEventListener('echo:ready', onReady);
            callback(window.Echo);
            return;
        }

        if (Date.now() - started >= timeoutMs) {
            window.clearInterval(poll);
            window.removeEventListener('echo:ready', onReady);
            callback(null);
        }
    }, 50);
};

function meta(name) {
    return document.querySelector(`meta[name="${name}"]`)?.getAttribute('content') || '';
}

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY || meta('reverb-app-key');
if (reverbKey) {
    const envHost = import.meta.env.VITE_REVERB_HOST || meta('reverb-host');
    // Prefer the page hostname so LAN IPs (e.g. 192.168.x.x) reach Reverb on this PC.
    const wsHost = !envHost || envHost === 'localhost' || envHost === '127.0.0.1'
        ? window.location.hostname
        : envHost;
    const wsPort = Number(import.meta.env.VITE_REVERB_PORT || meta('reverb-port') || 8080);
    const scheme = import.meta.env.VITE_REVERB_SCHEME || meta('reverb-scheme') || 'http';

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost,
        wsPort,
        wssPort: wsPort,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': window.getCsrfToken(),
            },
        },
    });

    window.dispatchEvent(new CustomEvent('echo:ready', { detail: window.Echo }));
}
