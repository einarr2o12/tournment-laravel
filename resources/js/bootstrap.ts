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
 * Reverb connection settings shared from the backend via Inertia props
 * (HandleInertiaRequests::share() -> 'reverb'). Resolved at RUNTIME from
 * backend env, so the reverb domain is not baked into the Vite build.
 */
export interface ReverbConfig {
  key: string;
  host: string;
  port?: number | null;
  scheme?: string | null;
}

/**
 * Lazily create the Echo client. Call this from pages that actually need
 * real-time channels (referee scoring, public live view) — NOT at boot.
 *
 * Booting Echo eagerly makes every page (login, admin shell, etc.) open
 * a WS to Reverb, which spams the console when Reverb isn't running.
 *
 * Pass the shared `reverb` prop from Inertia (`usePage().props.reverb`).
 * Returns null when no config is available (key/host missing) so callers
 * can fall back to polling.
 */
export function connectEcho(
  config?: ReverbConfig | null,
): Echo<'reverb'> | null {
  if (typeof window === 'undefined') return null;
  if (window.Echo) return window.Echo;

  const key = config?.key;
  const host = config?.host;
  if (!key || !host) return null;

  const port = Number(config?.port ?? 443);
  const forceTLS = (config?.scheme ?? 'https') === 'https';

  window.Echo = new Echo({
    broadcaster: 'reverb',
    key,
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS,
    enabledTransports: ['ws', 'wss'],
  });

  return window.Echo;
}
