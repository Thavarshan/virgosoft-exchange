import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { pusherAuthorizer } from './echo-authorizer';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        __e2eEcho?: {
            disconnect: () => void;
            connect: () => void;
        };
    }
}

const isBrowser = typeof window !== 'undefined';

if (isBrowser) {
    window.Pusher = Pusher;
}

export const isEchoConfigured = Boolean(import.meta.env.VITE_PUSHER_APP_KEY);

export const echo =
    isBrowser && isEchoConfigured
        ? new Echo({
              broadcaster: 'pusher',
              key: import.meta.env.VITE_PUSHER_APP_KEY,
              cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
              wsHost: import.meta.env.VITE_PUSHER_HOST || undefined,
              wsPort: Number(import.meta.env.VITE_PUSHER_PORT || 80),
              wssPort: Number(import.meta.env.VITE_PUSHER_PORT || 443),
              forceTLS:
                  (import.meta.env.VITE_PUSHER_SCHEME || 'https') === 'https',
              enabledTransports: ['ws', 'wss'],
              authEndpoint: '/broadcasting/auth',
              authorizer: pusherAuthorizer,
              withCredentials: true,
          })
        : null;

if (isBrowser && import.meta.env.VITE_E2E === 'true' && echo) {
    window.__e2eEcho = {
        disconnect: () => echo?.disconnect(),
        connect: () => echo?.connector.pusher.connect(),
    };
}

export function userChannel(userId: number) {
    return echo?.private(`user.${userId}`);
}
