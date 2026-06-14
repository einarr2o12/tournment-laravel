<?php

declare(strict_types=1);

namespace App\Http\Requests\Referee;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate a walkover declaration. The winning team must be one of the two
 * teams currently assigned to the match (enforced inside ScoringService).
 */
class WalkoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The Vue scoring page posts camelCase `winnerTeamId`; normalize it
     * before validation so both naming styles are accepted.
     */
    protected function prepareForValidation(): void
    {
        if ($this->missing('winner_team_id') && $this->filled('winnerTeamId')) {
            $this->merge(['winner_team_id' => $this->input('winnerTeamId')]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'winner_team_id' => ['required', 'string', 'uuid', 'exists:teams,id'],
        ];
    }

    public function winnerTeamId(): string
    {
        return (string) $this->input('winner_team_id');
    }
}
