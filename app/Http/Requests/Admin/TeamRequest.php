<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate a team write. A team belongs to a category and has 1 (singles) or
 * 2 (doubles) players, attached in order via the `team_players.position`
 * pivot. The form posts `playerIds` as an ordered array.
 */
class TeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->missing('category_id') && $this->filled('categoryId')) {
            $this->merge(['category_id' => $this->input('categoryId')]);
        }
        if ($this->missing('display_name') && $this->filled('displayName')) {
            $this->merge(['display_name' => $this->input('displayName')]);
        }
        if ($this->missing('player_ids') && $this->filled('playerIds')) {
            $this->merge(['player_ids' => $this->input('playerIds')]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'string', 'uuid', 'exists:categories,id'],
            'display_name' => ['required', 'string', 'max:120'],
            'seed' => ['nullable', 'integer', 'min:1'],
            'player_ids' => ['required', 'array', 'min:1', 'max:2'],
            'player_ids.*' => ['required', 'string', 'uuid', 'distinct', 'exists:players,id'],
        ];
    }

    /**
     * Column attributes for the team row (excludes the pivot relation).
     *
     * @return array<string, mixed>
     */
    public function teamAttributes(): array
    {
        return [
            'category_id' => $this->input('category_id'),
            'display_name' => $this->input('display_name'),
            'seed' => $this->input('seed'),
        ];
    }

    /**
     * Ordered list of player UUIDs (position is derived from array order).
     *
     * @return list<string>
     */
    public function playerIds(): array
    {
        /** @var array<int, string> $ids */
        $ids = $this->input('player_ids', []);

        return array_values($ids);
    }
}
