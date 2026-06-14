<?php

declare(strict_types=1);

namespace App\Http\Requests\Referee;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate the body of a point-scoring request. Only `scoring_team_id` is
 * required — it must reference one of the two teams on the live match (the
 * controller checks set-membership via the {@see App\Services\Scoring\ScoringService}).
 */
class ScorePointRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is delegated to controller / middleware
        // (referee assignment + ADMIN bypass).
        return true;
    }

    /**
     * The Vue scoring page posts camelCase `scoringTeamId`; normalize it
     * before validation so both naming styles are accepted.
     */
    protected function prepareForValidation(): void
    {
        if ($this->missing('scoring_team_id') && $this->filled('scoringTeamId')) {
            $this->merge(['scoring_team_id' => $this->input('scoringTeamId')]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'scoring_team_id' => ['required', 'string', 'uuid', 'exists:teams,id'],
        ];
    }

    public function scoringTeamId(): string
    {
        return (string) $this->input('scoring_team_id');
    }
}
