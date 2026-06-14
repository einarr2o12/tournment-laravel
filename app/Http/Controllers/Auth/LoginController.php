<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    /**
     * Render the Inertia login page.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => false,
            'status' => session('status'),
        ]);
    }

    /**
     * Handle a login request to the application.
     *
     * Session-based auth via the `web` guard. Sanctum SPA reuses the
     * same session cookie for stateful API access.
     *
     * @throws ValidationException
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $username = $request->username();
        $password = $request->password();

        /** @var User|null $user */
        $user = User::query()->where('username', $username)->first();

        if (! $user || ! $user->active || ! Hash::check($password, $user->password_hash)) {
            throw ValidationException::withMessages([
                'username' => __('auth.failed'),
            ]);
        }

        Auth::guard('web')->login($user, $request->remember());

        // Mitigate session fixation.
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        return redirect()->intended($this->redirectPathFor($user));
    }

    private function redirectPathFor(User $user): string
    {
        return match ($user->role->value) {
            'ADMIN' => '/admin',
            'REFEREE' => '/referee',
            default => '/',
        };
    }
}
