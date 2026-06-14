<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->missing('tournament_id') && $this->filled('tournamentId')) {
            $this->merge(['tournament_id' => $this->input('tournamentId')]);
        }
        if ($this->missing('full_name') && $this->filled('fullName')) {
            $this->merge(['full_name' => $this->input('fullName')]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tournament_id' => ['required', 'string', 'uuid', 'exists:tournaments,id'],
            'full_name' => ['required', 'string', 'max:120'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'club' => ['nullable', 'string', 'max:120'],
            'contact' => ['nullable', 'string', 'max:120'],
        ];
    }
}
