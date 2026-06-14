<?php

declare(strict_types=1);

namespace App\Services\Scoring;

use App\Enums\MatchStage;
use App\Enums\MatchStatus;
use App\Events\MatchUpdated;
use App\Models\MatchModel as GameMatch;
use App\Models\ScoreEvent;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Match scoring orchestrator. Wraps DB writes in a transaction and emits
 * {@see MatchUpdated} after every observable state change so the realtime
 * layer can broadcast to spectators / referees.
 *
 * The score_events table is the append-only source of truth; match_sets is a
 * denormalized snapshot rebuilt from replaying the kept events through
 * {@see ScoringRules::scorePoint()}.
 */
final class ScoringService
{
    /**
     * Begin a scheduled match. Resets any stale sets/events from a prior
     * (aborted) start and creates set #1.
     */
    public function startMatch(GameMatch $match): GameMatch
    {
        if ($match->team_a_id === null || $match->team_b_id === null) {
            throw ValidationException::withMessages([
                'match' => 'Match has no opponents assigned yet',
            ]);
        }

        if ($match->status === MatchStatus::IN_PROGRESS) {
            return $match->fresh($this->matchEagerLoads());
        }

        if ($match->status !== MatchStatus::SCHEDULED) {
            throw ValidationException::withMessages([
                'match' => "Cannot start a match in {$match->status->value} state",
            ]);
        }

        DB::transaction(function () use ($match): void {
            $match->sets()->delete();
            $match->scoreEvents()->delete();

            $now = Carbon::now();
            $match->sets()->create([
                'set_number' => 1,
                'started_at' => $now,
            ]);

            $match->forceFill([
                'status' => MatchStatus::IN_PROGRESS,
                'started_at' => $now,
                'winner_id' => null,
                'completed_at' => null,
            ])->save();
        });

        return $this->broadcast($match);
    }

    /**
     * Record a single point for the given team. Updates the snapshot match_set
     * row, appends a ScoreEvent, and (when the match completes) propagates
     * winner forward to the bracket plus loser to the third-place playoff for
     * semifinal completions.
     */
    public function recordPoint(GameMatch $match, Team $scoringTeam, ?User $scoredBy = null): GameMatch
    {
        $match->loadMissing('tournament');

        if ($match->status !== MatchStatus::IN_PROGRESS) {
            throw ValidationException::withMessages([
                'match' => 'Match is not in progress',
            ]);
        }

        if ($scoringTeam->id !== $match->team_a_id && $scoringTeam->id !== $match->team_b_id) {
            throw ValidationException::withMessages([
                'scoring_team' => 'Scoring team is not part of this match',
            ]);
        }

        $side = $scoringTeam->id === $match->team_a_id ? ScoringRules::SIDE_A : ScoringRules::SIDE_B;
        $config = $this->configForMatch($match);

        $previousState = $this->computeStateFromDb($match, $config);
        $nextState = ScoringRules::scorePoint($previousState, $side, $config);

        DB::transaction(function () use ($match, $scoringTeam, $scoredBy, $side, $previousState, $nextState): void {
            $currentSetNumber = $nextState['currentSetIndex'] + 1;
            $prevSetCompleted = count($nextState['sets']) !== count($previousState['sets'])
                || $nextState['matchWinner'] !== $previousState['matchWinner'];

            // The set we just scored on. If the set transitioned, the just-scored
            // set is the previous one (now closed); otherwise it's the current
            // still-in-progress set.
            $justScoredSetNumber = $prevSetCompleted
                ? $previousState['currentSetIndex'] + 1
                : $currentSetNumber;
            $justScoredSet = $nextState['sets'][$justScoredSetNumber - 1];

            $now = Carbon::now();
            $setWinnerId = $prevSetCompleted
                ? ($side === ScoringRules::SIDE_A ? $match->team_a_id : $match->team_b_id)
                : null;

            $existingSet = $match->sets()
                ->where('set_number', $justScoredSetNumber)
                ->first();

            $match->sets()->updateOrCreate(
                ['set_number' => $justScoredSetNumber],
                [
                    'team_a_score' => $justScoredSet['teamAScore'],
                    'team_b_score' => $justScoredSet['teamBScore'],
                    'winner_id' => $setWinnerId,
                    'started_at' => $existingSet?->started_at ?? $now,
                    'completed_at' => $prevSetCompleted ? $now : null,
                ]
            );

            // If a new set was started, ensure a row exists for it.
            if ($prevSetCompleted && $nextState['matchWinner'] === null) {
                $match->sets()->updateOrCreate(
                    ['set_number' => $currentSetNumber],
                    ['started_at' => $now]
                );
            }

            // Append the audit event (the source of truth for replay/undo).
            $match->scoreEvents()->create([
                'set_number' => $justScoredSetNumber,
                'scoring_team_id' => $scoringTeam->id,
                'scored_by_user_id' => $scoredBy?->id,
                'team_a_score_after' => $justScoredSet['teamAScore'],
                'team_b_score_after' => $justScoredSet['teamBScore'],
                'scored_at' => $now,
            ]);

            // Complete the match if a winner has emerged.
            if ($nextState['matchWinner'] !== null) {
                $winnerId = $nextState['matchWinner'] === ScoringRules::SIDE_A
                    ? $match->team_a_id
                    : $match->team_b_id;
                $loserId = $nextState['matchWinner'] === ScoringRules::SIDE_A
                    ? $match->team_b_id
                    : $match->team_a_id;

                $match->forceFill([
                    'status' => MatchStatus::COMPLETED,
                    'winner_id' => $winnerId,
                    'completed_at' => $now,
                ])->save();

                $this->propagateWinner($match, $winnerId);
                $this->propagateLoser($match, $loserId);
            }
        });

        return $this->broadcast($match);
    }

    /**
     * Undo the most recent (non-undone) point. Flips the event's `undone`
     * flag, rebuilds the match_set snapshots from the remaining events, and
     * reverts the match to IN_PROGRESS (clearing bracket propagation) if it
     * had already been completed.
     */
    public function undoLastPoint(GameMatch $match): GameMatch
    {
        $match->loadMissing('tournament');

        $lastEvent = $match->scoreEvents()
            ->where('undone', false)
            ->orderByDesc('scored_at')
            ->first();

        if ($lastEvent === null) {
            throw ValidationException::withMessages([
                'match' => 'No points to undo',
            ]);
        }

        $config = $this->configForMatch($match);

        DB::transaction(function () use ($match, $lastEvent, $config): void {
            $lastEvent->forceFill(['undone' => true])->save();

            $events = $match->scoreEvents()
                ->where('undone', false)
                ->orderBy('scored_at')
                ->get();

            $replayEvents = $events->map(fn (ScoreEvent $event): array => [
                'side' => $event->scoring_team_id === $match->team_a_id
                    ? ScoringRules::SIDE_A
                    : ScoringRules::SIDE_B,
            ])->all();

            $state = $replayEvents === []
                ? ScoringRules::emptyMatchState()
                : ScoringRules::replayPoints($replayEvents, $config);

            // Reset all sets to match the replayed state.
            $match->sets()->delete();
            $now = Carbon::now();

            foreach ($state['sets'] as $index => $set) {
                $setNumber = $index + 1;
                $isClosed = $state['matchWinner'] !== null || $index < $state['currentSetIndex'];

                $winnerId = null;
                if ($isClosed) {
                    $aWon = $set['teamAScore'] > $set['teamBScore'];
                    $winnerId = $aWon ? $match->team_a_id : $match->team_b_id;
                }

                $match->sets()->create([
                    'set_number' => $setNumber,
                    'team_a_score' => $set['teamAScore'],
                    'team_b_score' => $set['teamBScore'],
                    'winner_id' => $winnerId,
                    'started_at' => $now,
                    'completed_at' => $isClosed ? $now : null,
                ]);
            }

            // Flip back to IN_PROGRESS if the undo crossed the completion line.
            if ($match->status === MatchStatus::COMPLETED) {
                $match->forceFill([
                    'status' => MatchStatus::IN_PROGRESS,
                    'winner_id' => null,
                    'completed_at' => null,
                ])->save();

                $this->clearWinnerInNextMatch($match);
                $this->clearLoserInNextMatch($match);
            }
        });

        return $this->broadcast($match);
    }

    /**
     * Declare a walkover. Marks the match WALKOVER, sets the winner, and
     * propagates the winner to the next bracket match (no third-place
     * loser-propagation: walkovers don't produce a competitive bronze slot).
     */
    public function declareWalkover(GameMatch $match, Team $winner): GameMatch
    {
        if ($winner->id !== $match->team_a_id && $winner->id !== $match->team_b_id) {
            throw ValidationException::withMessages([
                'winner' => 'Winner must be one of the match teams',
            ]);
        }

        DB::transaction(function () use ($match, $winner): void {
            $match->forceFill([
                'status' => MatchStatus::WALKOVER,
                'winner_id' => $winner->id,
                'completed_at' => Carbon::now(),
            ])->save();

            $this->propagateWinner($match, $winner->id);
        });

        return $this->broadcast($match);
    }

    /**
     * Rebuild the live match score state by replaying the kept events.
     *
     * @param  array{pointsToWin:int,setsToWin:int,deuceCap:int} $config
     * @return array{
     *     sets: list<array{teamAScore:int,teamBScore:int}>,
     *     currentSetIndex: int,
     *     setsWonA: int,
     *     setsWonB: int,
     *     matchWinner: 'A'|'B'|null
     * }
     */
    private function computeStateFromDb(GameMatch $match, array $config): array
    {
        $events = $match->scoreEvents()
            ->where('undone', false)
            ->orderBy('scored_at')
            ->get(['scoring_team_id']);

        if ($events->isEmpty()) {
            return ScoringRules::emptyMatchState();
        }

        $replay = $events->map(fn (ScoreEvent $event): array => [
            'side' => $event->scoring_team_id === $match->team_a_id
                ? ScoringRules::SIDE_A
                : ScoringRules::SIDE_B,
        ])->all();

        return ScoringRules::replayPoints($replay, $config);
    }

    /**
     * Stage-aware scoring config for a match. Public so the admin bulk-result
     * path ({@see AdminResultService}) can validate entered scores against the
     * same targets the live scorer uses.
     *
     * @return array{pointsToWin:int,setsToWin:int,deuceCap:int}
     */
    public function configForMatch(GameMatch $match): array
    {
        $tournament = $match->tournament;

        // Group-stage matches use the tournament's group-scoring overrides when
        // present; any individual override left null falls back to the main
        // (knockout) value for that field. All other stages use the main config.
        if ($match->stage === MatchStage::GROUP && $tournament->group_points_to_win !== null) {
            return [
                'pointsToWin' => (int) ($tournament->group_points_to_win ?? $tournament->points_to_win),
                'setsToWin' => (int) ($tournament->group_sets_to_win ?? $tournament->sets_to_win),
                'deuceCap' => (int) ($tournament->group_deuce_cap ?? $tournament->deuce_cap),
            ];
        }

        return [
            'pointsToWin' => (int) $tournament->points_to_win,
            'setsToWin' => (int) $tournament->sets_to_win,
            'deuceCap' => (int) $tournament->deuce_cap,
        ];
    }

    /**
     * Push the winner of this match into the appropriate slot of its
     * next-bracket match. Even bracket slots feed team_a; odd slots feed
     * team_b. (Slot 1 -> team_a, slot 2 -> team_b, slot 3 -> team_a, ...)
     *
     * Public so {@see AdminResultService} can reuse identical propagation for
     * admin bulk results.
     */
    public function propagateWinner(GameMatch $match, ?string $winnerId): void
    {
        if ($match->next_match_id === null || $winnerId === null) {
            return;
        }

        $nextMatch = GameMatch::query()->find($match->next_match_id);
        if ($nextMatch === null) {
            return;
        }

        $slot = $match->bracket_slot ?? 1;
        $isFirstFeeder = (($slot - 1) % 2) === 0;

        $nextMatch->forceFill(
            $isFirstFeeder
                ? ['team_a_id' => $winnerId]
                : ['team_b_id' => $winnerId]
        )->save();
    }

    /**
     * Push the loser of this match into the appropriate slot of its
     * loser-bracket match (the bronze / third-place playoff). Uses the SAME
     * bracket_slot parity routing as {@see propagateWinner}: odd feeder slot
     * (1, 3, …) feeds team_a, even (2, 4, …) feeds team_b — so SF1's loser
     * lands in bronze team_a and SF2's loser in bronze team_b. To stay robust
     * we only fill an empty slot (team_a if empty, else team_b).
     *
     * Public so {@see AdminResultService} can reuse identical propagation.
     */
    public function propagateLoser(GameMatch $match, ?string $loserId): void
    {
        if ($match->loser_next_match_id === null || $loserId === null) {
            return;
        }

        $loserMatch = GameMatch::query()->find($match->loser_next_match_id);
        if ($loserMatch === null) {
            return;
        }

        $slot = $match->bracket_slot ?? 1;
        $isFirstFeeder = (($slot - 1) % 2) === 0;

        // Prefer the parity slot; fall back to the other if already taken so a
        // loser is never silently dropped.
        if ($isFirstFeeder && $loserMatch->team_a_id === null) {
            $loserMatch->forceFill(['team_a_id' => $loserId])->save();
        } elseif (! $isFirstFeeder && $loserMatch->team_b_id === null) {
            $loserMatch->forceFill(['team_b_id' => $loserId])->save();
        } elseif ($loserMatch->team_a_id === null) {
            $loserMatch->forceFill(['team_a_id' => $loserId])->save();
        } elseif ($loserMatch->team_b_id === null) {
            $loserMatch->forceFill(['team_b_id' => $loserId])->save();
        }
    }

    /**
     * When undoing the winning point of a completed match, also clear the
     * propagated slot in the next bracket match so the bracket stays
     * consistent. Public so {@see AdminResultService} can clear prior
     * propagation before overwriting or resetting a result.
     */
    public function clearWinnerInNextMatch(GameMatch $match): void
    {
        if ($match->next_match_id === null) {
            return;
        }

        $nextMatch = GameMatch::query()->find($match->next_match_id);
        if ($nextMatch === null) {
            return;
        }

        $slot = $match->bracket_slot ?? 1;
        $isFirstFeeder = (($slot - 1) % 2) === 0;

        $nextMatch->forceFill(
            $isFirstFeeder ? ['team_a_id' => null] : ['team_b_id' => null]
        )->save();
    }

    /**
     * Mirror of {@see clearWinnerInNextMatch} for the loser bracket: when a
     * completed match is undone back to IN_PROGRESS, null out the slot in the
     * bronze match that this match's loser had filled. Public so
     * {@see AdminResultService} can clear prior loser propagation.
     */
    public function clearLoserInNextMatch(GameMatch $match): void
    {
        if ($match->loser_next_match_id === null) {
            return;
        }

        $loserMatch = GameMatch::query()->find($match->loser_next_match_id);
        if ($loserMatch === null) {
            return;
        }

        $slot = $match->bracket_slot ?? 1;
        $isFirstFeeder = (($slot - 1) % 2) === 0;

        $loserMatch->forceFill(
            $isFirstFeeder ? ['team_a_id' => null] : ['team_b_id' => null]
        )->save();
    }

    /**
     * Refresh the match with the standard eager-loads and emit MatchUpdated.
     */
    private function broadcast(GameMatch $match): GameMatch
    {
        $fresh = $match->fresh($this->matchEagerLoads()) ?? $match;
        MatchUpdated::dispatch($fresh);

        return $fresh;
    }

    /**
     * @return list<string>
     */
    private function matchEagerLoads(): array
    {
        return [
            'tournament',
            'category',
            'court',
            'group',
            'teamA',
            'teamB',
            'winner',
            'sets',
        ];
    }

    /**
     * Quote a Carbon instance as a SQL literal usable inside a raw expression.
     * Used so that `started_at` is only set if it was previously null.
     */
    private function quoteTimestamp(Carbon $timestamp): string
    {
        return "'" . $timestamp->toDateTimeString() . "'";
    }
}
