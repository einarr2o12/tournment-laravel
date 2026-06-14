<?php

declare(strict_types=1);

namespace Tests\Feature\Scoring;

use App\Enums\MatchStatus;
use App\Models\Category;
use App\Models\Court;
use App\Models\MatchModel;
use App\Models\MatchSet;
use Database\Factories\MatchFactory;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Scoring\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature suite for BWF 3x15 (best-of-3 sets, 15-point sets, deuce cap 21)
 * scoring through the live ScoringService.point() + undo() pipeline.
 *
 * Uses the actual service (not a mock) end-to-end so we exercise the
 * config-from-tournament path, the match_sets snapshot writes, the
 * score_events append-only log, and the COMPLETED transitions.
 */
class BwfThreeFifteenScoringTest extends TestCase
{
    use RefreshDatabase;

    private ScoringService $service;

    private Tournament $tournament;

    private Category $category;

    private Court $court;

    private Team $teamA;

    private Team $teamB;

    private User $referee;

    private MatchModel $match;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ScoringService();

        $this->tournament = Tournament::factory()->create([
            'points_to_win' => 15,
            'sets_to_win' => 2,
            'deuce_cap' => 21,
        ]);

        $this->category = Category::factory()->create([
            'tournament_id' => $this->tournament->id,
        ]);

        $this->court = Court::factory()->forTournament($this->tournament)->create();

        $this->teamA = Team::factory()->forCategory($this->category)->named('Team A')->create();
        $this->teamB = Team::factory()->forCategory($this->category)->named('Team B')->create();

        $this->referee = User::factory()->referee()->create();

        $this->match = MatchFactory::new()
            ->forTournament($this->tournament)
            ->forCategory($this->category)
            ->onCourt($this->court)
            ->withTeams($this->teamA, $this->teamB)
            ->scheduled()
            ->create();

        $this->service->startMatch($this->match);
        $this->match->refresh();
    }

    /**
     * Feed points to bring the OPEN set to (aPoints, bPoints) without
     * accidentally closing it. Caller must request a non-set-winning score
     * pair (otherwise the last point belongs in the next set).
     *
     * Strategy: keep both sides moving in lock-step, never letting either side
     * cross the BWF-rule win threshold for the current target.
     */
    private function feedSetScores(int $aTarget, int $bTarget): void
    {
        $a = 0;
        $b = 0;
        $guard = 0;
        while ($a < $aTarget || $b < $bTarget) {
            $guard++;
            if ($guard > 200) {
                $this->fail("feedSetScores stuck at a={$a} b={$b} (targets {$aTarget}/{$bTarget})");
            }

            // Prefer to advance the side that is further from its target, while
            // making sure the next point does not satisfy a BWF set-winning
            // condition before we reach the target.
            $candidates = [];
            if ($a < $aTarget && ! $this->wouldCloseSet($a + 1, $b, $aTarget, $bTarget)) {
                $candidates[] = 'A';
            }
            if ($b < $bTarget && ! $this->wouldCloseSet($a, $b + 1, $aTarget, $bTarget)) {
                $candidates[] = 'B';
            }

            if ($candidates === []) {
                $this->fail("No safe next point at a={$a} b={$b} targets {$aTarget}/{$bTarget}");
            }

            // Always pick the side that is furthest behind its target to keep
            // scores in step.
            $pick = $candidates[0];
            if (count($candidates) === 2) {
                $pick = ($aTarget - $a) >= ($bTarget - $b) ? 'A' : 'B';
            }

            if ($pick === 'A') {
                $this->service->recordPoint($this->match->refresh(), $this->teamA, $this->referee);
                $a++;
            } else {
                $this->service->recordPoint($this->match->refresh(), $this->teamB, $this->referee);
                $b++;
            }
        }
    }

    /**
     * Would (a, b) be a closed BWF set under (pointsToWin=15, deuceCap=21)?
     * Allow the target itself even if it equals a closing score — caller is
     * intentionally trying to land on that score.
     */
    private function wouldCloseSet(int $a, int $b, int $aTarget, int $bTarget): bool
    {
        if ($a === $aTarget && $b === $bTarget) {
            return false;
        }
        $deuceCap = 21;
        $pointsToWin = 15;
        if ($a >= $deuceCap && $a > $b) {
            return true;
        }
        if ($b >= $deuceCap && $b > $a) {
            return true;
        }
        if ($a >= $pointsToWin && ($a - $b) >= 2) {
            return true;
        }
        if ($b >= $pointsToWin && ($b - $a) >= 2) {
            return true;
        }
        return false;
    }

    private function currentSet(int $setNumber): MatchSet
    {
        return MatchSet::query()
            ->where('match_id', $this->match->id)
            ->where('set_number', $setNumber)
            ->firstOrFail();
    }

    /** a) Normal set win at 15-13. */
    public function test_normal_set_win_15_13(): void
    {
        $this->feedSetScores(15, 13);

        $set1 = $this->currentSet(1);
        $this->assertSame(15, $set1->team_a_score);
        $this->assertSame(13, $set1->team_b_score);
        $this->assertSame($this->teamA->id, $set1->winner_id, 'Set 1 should be won by Team A');
        $this->assertNotNull($set1->completed_at);

        // Match should NOT yet be complete (only 1 of 2 sets won).
        $this->match->refresh();
        $this->assertSame(MatchStatus::IN_PROGRESS, $this->match->status);
    }

    /** b) Deuce: 14-14 then 15-14 — set NOT yet won. */
    public function test_deuce_continues_at_15_14(): void
    {
        $this->feedSetScores(14, 14);
        // A scores once: 15-14
        $this->service->recordPoint($this->match->refresh(), $this->teamA, $this->referee);

        $set1 = $this->currentSet(1);
        $this->assertSame(15, $set1->team_a_score);
        $this->assertSame(14, $set1->team_b_score);
        $this->assertNull($set1->winner_id, '15-14 in deuce should NOT close the set');
        $this->assertNull($set1->completed_at);

        $this->match->refresh();
        $this->assertSame(MatchStatus::IN_PROGRESS, $this->match->status);
    }

    /** c) Deuce: 15-14 then 16-14 — A wins set. */
    public function test_deuce_two_point_lead_at_16_14(): void
    {
        $this->feedSetScores(14, 14);
        $this->service->recordPoint($this->match->refresh(), $this->teamA, $this->referee);
        $this->service->recordPoint($this->match->refresh(), $this->teamA, $this->referee);

        $set1 = $this->currentSet(1);
        $this->assertSame(16, $set1->team_a_score);
        $this->assertSame(14, $set1->team_b_score);
        $this->assertSame($this->teamA->id, $set1->winner_id, 'A should win set at 16-14');
        $this->assertNotNull($set1->completed_at);
    }

    /** d) Hard cap: 20-20 then 21-20 — A wins (1-point lead OK at cap). */
    public function test_hard_cap_wins_with_one_point_lead_at_21_20(): void
    {
        $this->feedSetScores(20, 20);
        $this->service->recordPoint($this->match->refresh(), $this->teamA, $this->referee);

        $set1 = $this->currentSet(1);
        $this->assertSame(21, $set1->team_a_score);
        $this->assertSame(20, $set1->team_b_score);
        $this->assertSame(
            $this->teamA->id,
            $set1->winner_id,
            'At deuce cap (21), a 1-point lead must close the set'
        );
        $this->assertNotNull($set1->completed_at);
    }

    /** e) Match win: A wins 2 sets, match → COMPLETED. */
    public function test_match_completes_when_a_wins_two_sets(): void
    {
        // Set 1: A wins 15-13
        $this->feedSetScores(15, 13);

        $this->match->refresh();
        $this->assertSame(MatchStatus::IN_PROGRESS, $this->match->status);

        // Set 2: A wins 15-10 (now in set 2 which was auto-created)
        $this->feedSetScores(15, 10);

        $this->match->refresh();
        $this->assertSame(MatchStatus::COMPLETED, $this->match->status);
        $this->assertSame($this->teamA->id, $this->match->winner_id);
        $this->assertNotNull($this->match->completed_at);

        $set2 = $this->currentSet(2);
        $this->assertSame($this->teamA->id, $set2->winner_id);
    }

    /** f) Undo reverses set win: A 15-13, undo, set re-opens to 14-13. */
    public function test_undo_reverses_set_win(): void
    {
        $this->feedSetScores(15, 13);

        $set1Before = $this->currentSet(1);
        $this->assertSame($this->teamA->id, $set1Before->winner_id);

        $this->service->undoLastPoint($this->match->refresh());

        $set1After = $this->currentSet(1);
        $this->assertSame(14, $set1After->team_a_score, 'A should drop back to 14');
        $this->assertSame(13, $set1After->team_b_score);
        $this->assertNull($set1After->winner_id, 'Set 1 should re-open after undo');
        $this->assertNull($set1After->completed_at);

        $this->match->refresh();
        $this->assertSame(MatchStatus::IN_PROGRESS, $this->match->status);
    }
}
