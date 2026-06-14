<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * Usage:
     *   Route::middleware('role:ADMIN')->...
     *   Route::middleware('role:ADMIN,REFEREE')->...
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException(
                'Unauthenticated.',
                ['web'],
                route('login', absolute: false),
            );
        }

        if ($roles === []) {
            return $next($request);
        }

        $userRole = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;

        foreach ($roles as $role) {
            if (strcasecmp($userRole, $role) === 0) {
                return $next($request);
            }
        }

        throw new AccessDeniedHttpException('Insufficient role.');
    }
}
