<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate an operator account write. `username` is the login handle (unique,
 * ignoring the record being edited). `password` is plaintext from the form —
 * required on create, optional on update — the controller hashes it into the
 * `password_hash` column.
 */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->missing('full_name') && $this->filled('fullName')) {
            $this->merge(['full_name' => $this->input('fullName')]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');
        $userId = $user?->getKey();

        $passwordRule = $userId !== null
            ? ['nullable', 'string', 'min:8', 'max:255']
            : ['required', 'string', 'min:8', 'max:255'];

        return [
            'username' => [
                'required', 'string', 'max:60',
                Rule::unique('users', 'username')->ignore($userId),
            ],
            'full_name' => ['nullable', 'string', 'max:120'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'active' => ['required', 'boolean'],
            'password' => $passwordRule,
        ];
    }
}
