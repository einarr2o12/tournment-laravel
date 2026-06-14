<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manage operator accounts (ADMIN + REFEREE). Auth is by `username`; the
 * password column is `password_hash`. The form posts a plaintext `password`
 * which we hash here (required on create, optional on update).
 */
class UserController extends Controller
{
    /**
     * GET /manage/users
     */
    public function index(): Response
    {
        $users = User::query()
            ->select(['id', 'username', 'full_name', 'role', 'active', 'last_login_at'])
            ->orderBy('username')
            ->get()
            ->map(fn (User $u): array => $this->serialize($u))
            ->all();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
        ]);
    }

    /**
     * GET /manage/users/create
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Users/Form', [
            'user' => null,
            'roles' => UserRole::labels(),
        ]);
    }

    /**
     * POST /manage/users
     */
    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['password_hash'] = Hash::make((string) $request->input('password'));
        unset($data['password']);

        User::query()->create($data);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User created.');
    }

    /**
     * GET /manage/users/{user}/edit
     */
    public function edit(User $user): Response
    {
        return Inertia::render('Admin/Users/Form', [
            'user' => $this->serialize($user),
            'roles' => UserRole::labels(),
        ]);
    }

    /**
     * PUT /manage/users/{user}
     */
    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        // Password is optional on update — only re-hash when provided.
        if ($request->filled('password')) {
            $data['password_hash'] = Hash::make((string) $request->input('password'));
        }
        unset($data['password']);

        $user->update($data);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User updated.');
    }

    /**
     * DELETE /manage/users/{user}
     */
    public function destroy(User $user): RedirectResponse
    {
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(User $u): array
    {
        return [
            'id' => $u->getKey(),
            'username' => $u->username,
            'full_name' => $u->full_name,
            'role' => $u->role?->value,
            'active' => (bool) $u->active,
            'lastLoginAt' => $u->last_login_at?->toIso8601String(),
        ];
    }
}
