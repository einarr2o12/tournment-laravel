<?php

declare(strict_types=1);

namespace App\Services\Scoring;

use App\Enums\MatchStage;
use App\Enums\MatchStatus;
use App\Events\MatchUpdated;
use App\Models\MatchModel as GameMatch;
use App\Models\Team;
use App\Models\User;
use App\Services\BracketSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin-authoritative bulk result entry.
 *
 * Where {@see ScoringService} records a match point-by-point (referee flow),
 * this service lets an admin enter the FINAL set scores of a match in one shot,
 * derives the winner with {@see ScoringRules}, and persists match_sets +
 * winner + status, reusing ScoringService's bracket propagation/clear helpers.
 *
 * No score_events are written: an admin result is a bulk overwrite, not a
 * point-by-point audit trail. If a referee later needs point-by-point control,
 * they start fresh via the live scorer.
 */
final class AdminResultService
{
    public function __construct(
        private readonly ScoringService $scoring,
        private readonly BracketSeeder $bracketSeeder,
    ) {
    }

    /**
     * Record (or overwrite) a completed match result from admin-entered set
     * scores. Validates that each set has a determinable winner under the
     * stage-aware config, that the set count fits the format, and that the
     * match is actually decided (one side reached setsToWin with no extra
     * sets). Clears any prior result + downstream propagation first, writes the
     * new sets, sets the winner/status, propagates, and — for a group match
     * that completes the category's group stage — auto-resolves bracket seeds.
     *
     * @param  list<array{teamAScore:int,teamBScore:int}>  $sets
     *
     * @throws ValidationException
     */
    public function recordResult(GameMatch $match, array $sets, ?User $admin = null): void
    {
        $match->loadMissing('tournament');

        if ($match->team_a_id === null || $match->team_b_id === null) {
            throw ValidationException::withMessages([
                'sets' => 'Teams not determined yet for this match.',
            ]);
        }

        $config = $this->scoring->configForMatch($match);
        $setsToWin = $config['setsToWin'];
        $maxSets = ScoringRules::maxSets($config);

        $sets = array_values($sets);
        $count = count($sets);

        if ($count === 0) {
            throw ValidationException::withMessages([
                'sets' => 'At least one set score is required.',
            ]);
        }
        if ($count > $maxSets) {
            throw ValidationException::withMessages([
                'sets' => "Too many sets: this format is best-of-{$maxSets} (max {$maxSets} set(s)).",
            ]);
        }

        // Group stage = exactly 1 set; knockout best-of-3 = 2 or 3 sets.
        if ($setsToWin === 1 && $count !== 1) {
            throw ValidationException::withMessages([
                'sets' => 'Group matches are a single game — enter exactly one set.',
            ]);
        }
        if ($setsToWin > 1 && $count < $setsToWin) {
            throw ValidationException::withMessages([
                'sets' => "A decided match needs at least {$setsToWin} sets.",
            ]);
        }

        // Resolve each set's winner under the config and tally.
        $setWinners = [];
        $setsWonA = 0;
        $setsWonB = 0;

        foreach ($sets as $index => $set) {
            $a = (int) $set['teamAScore'];
            $b = (int) $set['teamBScore'];
            $winnerSide = ScoringRules::setWinner(
                ['teamAScore' => $a, 'teamBScore' => $b],
                $config,
            );

            if ($winnerSide === null) {
                $setNo = $index + 1;
                throw ValidationException::withMessages([
                    'sets' => "Set {$setNo} ({$a}-{$b}) has no valid winner under the "
                        . "{$config['pointsToWin']}-point / deuce-{$config['deuceCap']} rule.",
                ]);
            }

            $setWinners[$index] = $winnerSide;
            if ($winnerSide === ScoringRules::SIDE_A) {
                $setsWonA++;
            } else {
                $setsWonB++;
            }
        }

        // The match must be DECIDED: exactly one side reached setsToWin.
        if ($setsWonA < $setsToWin && $setsWonB < $setsToWin) {
            throw ValidationException::withMessages([
                'sets' => "Match not decided: neither team has won {$setsToWin} set(s).",
            ]);
        }
        if ($setsWonA >= $setsToWin && $setsWonB >= $setsToWin) {
            throw ValidationException::withMessages([
                'sets' => 'Both teams cannot win the match — check the set scores.',
            ]);
        }

        // No extra sets beyond the decider: the deciding set must be the last
        // set, i.e. the winner reaches setsToWin on the final entered set.
        $winnerSide = $setsWonA >= $setsToWin ? ScoringRules::SIDE_A : ScoringRules::SIDE_B;
        $winnerSetsBeforeLast = 0;
        for ($i = 0; $i < $count - 1; $i++) {
            if ($setWinners[$i] === $winnerSide) {
                $winnerSetsBeforeLast++;
            }
        }
        if ($winnerSetsBeforeLast >= $setsToWin || $setWinners[$count - 1] !== $winnerSide) {
            throw ValidationException::withMessages([
                'sets' => 'Extra sets after the match was already decided — remove the dead sets.',
            ]);
        }

        $winnerId = $winnerSide === ScoringRules::SIDE_A ? $match->team_a_id : $match->team_b_id;
        $loserId = $winnerSide === ScoringRules::SIDE_A ? $match->team_b_id : $match->team_a_id;

        DB::transaction(function () use ($match, $sets, $setWinners, $winnerId, $loserId): void {
            // 1. Clear any prior result + downstream propagation. If this match
            //    already had a propagated winner/loser, null those slots first.
            if ($match->winner_id !== null
                || in_array($match->status, [MatchStatus::COMPLETED, MatchStatus::WALKOVER], true)) {
                $this->scoring->clearWinnerInNextMatch($match);
                $this->scoring->clearLoserInNextMatch($match);
            }
            $match->sets()->delete();
            $match->scoreEvents()->delete();

            // 2. Write the new set rows.
            $now = Carbon::now();
            foreach ($sets as $index => $set) {
                $setNumber = $index + 1;
                $a = (int) $set['teamAScore'];
                $b = (int) $set['teamBScore'];
                $setWinnerId = $setWinners[$index] === ScoringRules::SIDE_A
                    ? $match->team_a_id
                    : $match->team_b_id;

                $match->sets()->create([
                    'set_number' => $setNumber,
                    'team_a_score' => $a,
                    'team_b_score' => $b,
                    'winner_id' => $setWinnerId,
                    'started_at' => $now,
                    'completed_at' => $now,
                ]);
            }

            // 3. Mark the match complete with the derived winner.
            $match->forceFill([
                'status' => MatchStatus::COMPLETED,
                'winner_id' => $winnerId,
                'started_at' => $match->started_at ?? $now,
                'completed_at' => $now,
            ])->save();

            // 4. Propagate winner forward + loser to the bronze playoff.
            $this->scoring->propagateWinner($match, $winnerId);
            $this->scoring->propagateLoser($match, $loserId);
        });

        // 5. If this was the last group match of its category, auto-resolve the
        //    knockout seeds (runs in its own transaction inside the seeder).
        if ($match->stage === MatchStage::GROUP) {
            $category = $match->category;
            if ($category !== null) {
                $this->bracketSeeder->resolveForCategory($category);
            }
        }

        $this->broadcast($match);
    }

    /**
     * Declare a walkover for the given winner. Thin wrapper over
     * {@see ScoringService::declareWalkover} so the admin controller has one
     * collaborator. Clears any prior result first so a re-decided match does
     * not leave stale sets behind.
     *
     * @throws ValidationException
     */
    public function walkover(GameMatch $match, Team $winner, ?User $admin = null): void
    {
        if ($winner->id !== $match->team_a_id && $winner->id !== $match->team_b_id) {
            throw ValidationException::withMessages([
                'winner_team_id' => 'Winner must be one of the match teams.',
            ]);
        }

        // Drop any previously entered sets/events so a walkover never carries a
        // leftover score snapshot. declareWalkover then re-propagates.
        DB::transaction(function () use ($match): void {
            if ($match->winner_id !== null
                || in_array($match->status, [MatchStatus::COMPLETED, MatchStatus::WALKOVER], true)) {
                $this->scoring->clearWinnerInNextMatch($match);
                $this->scoring->clearLoserInNextMatch($match);
            }
            $match->sets()->delete();
            $match->scoreEvents()->delete();
        });

        $this->scoring->declareWalkover($match, $winner);

        if ($match->stage === MatchStage::GROUP && $match->category !== null) {
            $this->bracketSeeder->resolveForCategory($match->category);
        }
    }

    /**
     * Clear a match result back to a clean SCHEDULED state: delete sets/events,
     * null winner/completed_at/started_at, and clear downstream propagation.
     */
    public function resetResult(GameMatch $match): void
    {
        DB::transaction(function () use ($match): void {
            // Clear any propagated winner/loser before wiping the result.
            $this->scoring->clearWinnerInNextMatch($match);
            $this->scoring->clearLoserInNextMatch($match);

            $match->sets()->delete();
            $match->scoreEvents()->delete();

            $match->forceFill([
                'status' => MatchStatus::SCHEDULED,
                'winner_id' => null,
                'started_at' => null,
                'completed_at' => null,
            ])->save();
        });

        $this->broadcast($match);
    }

    /**
     * Refresh and broadcast so the realtime layer sees the admin change.
     */
    private function broadcast(GameMatch $match): void
    {
        $fresh = $match->fresh([
            'tournament', 'category', 'court', 'group',
            'teamA', 'teamB', 'winner', 'sets',
        ]) ?? $match;

        MatchUpdated::dispatch($fresh);
    }
}
