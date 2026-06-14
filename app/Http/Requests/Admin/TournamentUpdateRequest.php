<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate tournament settings edits. Single-tournament focused — only the
 * settings change here, never the identity/lifecycle beyond status.
 */
class TournamentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->missing('start_date') && $this->filled('startDate')) {
            $this->merge(['start_date' => $this->input('startDate')]);
        }
        if ($this->missing('end_date') && $this->filled('endDate')) {
            $this->merge(['end_date' => $this->input('endDate')]);
        }
        if ($this->missing('points_to_win') && $this->filled('pointsToWin')) {
            $this->merge(['points_to_win' => $this->input('pointsToWin')]);
        }
        if ($this->missing('sets_to_win') && $this->filled('setsToWin')) {
            $this->merge(['sets_to_win' => $this->input('setsToWin')]);
        }
        if ($this->missing('deuce_cap') && $this->filled('deuceCap')) {
            $this->merge(['deuce_cap' => $this->input('deuceCap')]);
        }
        if ($this->missing('group_points_to_win') && $this->filled('groupPointsToWin')) {
            $this->merge(['group_points_to_win' => $this->input('groupPointsToWin')]);
        }
        if ($this->missing('group_sets_to_win') && $this->filled('groupSetsToWin')) {
            $this->merge(['group_sets_to_win' => $this->input('groupSetsToWin')]);
        }
        if ($this->missing('group_deuce_cap') && $this->filled('groupDeuceCap')) {
            $this->merge(['group_deuce_cap' => $this->input('groupDeuceCap')]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'venue' => ['nullable', 'string', 'max:160'],
            'format' => ['required', Rule::enum(TournamentFormat::class)],
            'status' => ['required', Rule::enum(TournamentStatus::class)],
            'points_to_win' => ['required', 'integer', 'min:1', 'max:99'],
            'sets_to_win' => ['required', 'integer', 'min:1', 'max:9'],
            'deuce_cap' => ['required', 'integer', 'min:1', 'max:99'],
            'group_points_to_win' => ['nullable', 'integer', 'min:1', 'max:99'],
            'group_sets_to_win' => ['nullable', 'integer', 'min:1', 'max:9'],
            'group_deuce_cap' => ['nullable', 'integer', 'min:1', 'max:99'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }
}
