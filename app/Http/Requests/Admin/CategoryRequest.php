<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\CategoryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
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
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tournament_id' => ['required', 'string', 'uuid', 'exists:tournaments,id'],
            'type' => ['required', Rule::enum(CategoryType::class)],
            'name' => ['required', 'string', 'max:120'],
        ];
    }
}
