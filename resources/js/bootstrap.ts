import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
  interface Window {
    axios: typeof axios;
    Echo?: Echo<'reverb'>;
    Pusher: typeof Pusher;
  }
}

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.Pusher = Pusher;

/**
 * Lazily create the Echo client. Call this from pages that actually need
 * real-time channels (referee scoring, public live view) — NOT at boot.
 *
 * Booting Echo eagerly makes every page (login, admin shell, etc.) open
 * a WS to Reverb, which spams the console when Reverb isn't running.
 */
export function connectEcho(): Echo<'reverb'> | null {
  if (typeof window === 'undefined') return null;
  if (window.Echo) return window.Echo;

  const key = import.meta.env.VITE_REVERB_APP_KEY;
  const host = import.meta.env.VITE_REVERB_HOST;
  if (!key || !host) return null;

  // Only attempt a websocket when the page is actually served from the
  // configured Reverb host. A phone hitting the app via ngrok has a
  // different hostname and can't reach ws://localhost:8080 — skipping
  // here lets callers fall back to polling instead of spamming the
  // console with doomed reconnect attempts.
  if (window.location.hostname !== host) return null;

  window.Echo = new Echo({
    broadcaster: 'reverb',
    key,
    wsHost: host,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
  });

  return window.Echo;
}
