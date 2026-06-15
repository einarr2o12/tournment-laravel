<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'username' => $request->user()->username,
                    'full_name' => $request->user()->full_name,
                    'fullName' => $request->user()->full_name,
                    'role' => $request->user()->role?->value,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            // Reverb (websocket) connection details for the frontend Echo
            // client. Sourced from runtime config so the production reverb
            // domain is NOT baked into the Vite build — changing it only
            // requires a backend env change + config:cache. Returns null
            // when key/host are absent so the frontend falls back to polling.
            'reverb' => function () {
                $key = config('broadcasting.connections.reverb.key');
                $host = config('broadcasting.connections.reverb.options.host');

                if (! $key || ! $host) {
                    return null;
                }

                return [
                    'key' => $key,
                    'host' => $host,
                    'port' => (int) config('broadcasting.connections.reverb.options.port', 443),
                    'scheme' => config('broadcasting.connections.reverb.options.scheme', 'https'),
                ];
            },
        ];
    }
}
