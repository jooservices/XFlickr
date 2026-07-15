import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

type EchoClient = Echo<'reverb'>;

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo?: EchoClient;
    }
}

export function isReverbConfigured(): boolean {
    return Boolean(import.meta.env.VITE_REVERB_APP_KEY);
}

export function getEcho(): EchoClient | null {
    if (! isReverbConfigured()) {
        return null;
    }

    if (window.Echo) {
        return window.Echo;
    }

    window.Pusher = Pusher;

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: String(import.meta.env.VITE_REVERB_APP_KEY),
        wsHost: String(import.meta.env.VITE_REVERB_HOST ?? window.location.hostname),
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        withCredentials: true,
    });

    return window.Echo;
}
