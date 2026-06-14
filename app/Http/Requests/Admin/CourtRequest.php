<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * CANONICAL ADMIN FORM REQUEST — clone this shape per writable resource.
 *   - authorize() returns true (the `role:ADMIN` route middleware is the gate).
 *   - prepareForValidation() normalizes camelCase payloads to snake_case so
 *     both naming styles are accepted (mirrors Referee\ScorePointRequest).
 *   - rules() keys are snake_case and reference real columns / enum values.
 */
class CourtRequest extends FormRequest
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
        if ($this->missing('display_order') && $this->filled('displayOrder')) {
            $this->merge(['display_order' => $this->input('displayOrder')]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'tournament_id' => ['required', 'string', 'uuid', 'exists:tournaments,id'],
            'name' => ['required', 'string', 'max:120'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['required', 'boolean'],
        ];
    }
}
