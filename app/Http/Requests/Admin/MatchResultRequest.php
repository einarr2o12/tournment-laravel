<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate an admin bulk match-result submission: a list of set scores. The
 * winner is derived server-side ({@see \App\Services\Scoring\AdminResultService}),
 * so only the raw per-set scores are validated here — the BWF set/match
 * validity (deuce, decided-match, set count) is enforced by the service against
 * the stage-aware config.
 */
class MatchResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize a `sets` array of {teamAScore, teamBScore} (camelCase). Accepts
     * snake_case `team_a_score`/`team_b_score` too and casts blank entries to 0.
     */
    protected function prepareForValidation(): void
    {
        $sets = $this->input('sets');

        if (! is_array($sets)) {
            return;
        }

        $normalized = [];
        foreach ($sets as $set) {
            if (! is_array($set)) {
                continue;
            }

            $a = $set['teamAScore'] ?? $set['team_a_score'] ?? null;
            $b = $set['teamBScore'] ?? $set['team_b_score'] ?? null;

            // Drop fully-empty rows so a trailing blank set doesn't fail count.
            if (($a === null || $a === '') && ($b === null || $b === '')) {
                continue;
            }

            $normalized[] = [
                'teamAScore' => $a === null || $a === '' ? 0 : (int) $a,
                'teamBScore' => $b === null || $b === '' ? 0 : (int) $b,
            ];
        }

        $this->merge(['sets' => $normalized]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'sets' => ['required', 'array', 'min:1'],
            'sets.*.teamAScore' => ['required', 'integer', 'min:0'],
            'sets.*.teamBScore' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sets.required' => 'Enter at least one set score.',
            'sets.*.teamAScore.required' => 'Each set needs a score for team A.',
            'sets.*.teamBScore.required' => 'Each set needs a score for team B.',
        ];
    }

    /**
     * The validated, normalized set list.
     *
     * @return list<array{teamAScore:int,teamBScore:int}>
     */
    public function sets(): array
    {
        /** @var list<array{teamAScore:int,teamBScore:int}> $sets */
        $sets = array_values($this->validated('sets', []));

        return $sets;
    }
}
