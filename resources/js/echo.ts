/**
 * Laravel Echo Configuration
 * Initializes real-time WebSocket connection to Laravel Reverb
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo<'reverb'>;
    }
}

window.Pusher = Pusher;

const reverbAppKey = import.meta.env.VITE_REVERB_APP_KEY as string | undefined;

if (reverbAppKey) {
    const reverbPort = import.meta.env.VITE_REVERB_PORT
        ? Number(import.meta.env.VITE_REVERB_PORT)
        : 8080;

    window.Echo = new Echo({
        broadcaster: 'reverb' as const,
        key: reverbAppKey,
        wsHost: import.meta.env.VITE_REVERB_HOST || 'localhost',
        wsPort: reverbPort,
        wssPort: reverbPort,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'] as ('ws' | 'wss')[],
    });
}

export default window.Echo;
