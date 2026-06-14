<?php

declare(strict_types=1);

namespace Tests\Feature\Scoring;

use App\Enums\MatchStage;
use App\Enums\MatchStatus;
use App\Models\Category;
use App\Models\Court;
use App\Models\MatchModel;
use App\Models\MatchSet;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Scoring\ScoringService;
use Database\Factories\MatchFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature suite proving STAGE-AWARE scoring through the live
 * ScoringService::startMatch() + recordPoint() pipeline.
 *
 * One tournament carries BOTH a main (knockout) config of 15/2 (deuce cap 21)
 * AND a group-stage override of 21/1 (deuce cap 30). The scoring engine must
 * pick the config from the MATCH STAGE:
 *   - GROUP matches    -> single game to 21, deuce cap 30, sets_to_win = 1.
 *   - SEMIFINAL matches -> best of 3 to 15, deuce cap 21, sets_to_win = 2.
 *
 * Uses the actual service (not a mock) end-to-end so we exercise the
 * config-from-tournament path, the match_sets snapshot writes, the
 * score_events append-only log, and the COMPLETED transitions.
 */
class GroupStageScoringTest extends TestCase
{
    use RefreshDatabase;

    private ScoringService $service;

    private Tournament $tournament;

    private Category $category;

    private Court $court;

    private Team $teamA;

    private Team $teamB;

    private User $referee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ScoringService();

        // Main (knockout) config = 15/2 deuce-cap 21.
        // Group override = 21/1 deuce-cap 30.
        $this->tournament = Tournament::factory()->create([
            'points_to_win' => 15,
            'sets_to_win' => 2,
            'deuce_cap' => 21,
            'group_points_to_win' => 21,
            'group_sets_to_win' => 1,
            'group_deuce_cap' => 30,
        ]);

        $this->category = Category::factory()->create([
            'tournament_id' => $this->tournament->id,
        ]);

        $this->court = Court::factory()->forTournament($this->tournament)->create();

        $this->teamA = Team::factory()->forCategory($this->category)->named('Team A')->create();
        $this->teamB = Team::factory()->forCategory($this->category)->named('Team B')->create();

        $this->referee = User::factory()->referee()->create();
    }

    /**
     * Create + start a match at the given stage and return it (refreshed).
     */
    private function startedMatch(MatchStage $stage): MatchModel
    {
        $match = MatchFactory::new()
            ->forTournament($this->tournament)
            ->forCategory($this->category)
            ->onCourt($this->court)
            ->stage($stage)
            ->withTeams($this->teamA, $this->teamB)
            ->scheduled()
            ->create();

        $this->service->startMatch($match);

        return $match->refresh();
    }

    /**
     * Feed points to bring the OPEN set of $match to (aTarget, bTarget) without
     * accidentally closing it. The caller passes the stage's scoring config so
     * the set-close guard matches the engine the match actually uses.
     *
     * @param  array{pointsToWin:int,deuceCap:int}  $config
     */
    private function feedSetScores(MatchModel $match, int $aTarget, int $bTarget, array $config): void
    {
        $a = 0;
        $b = 0;
        $guard = 0;
        while ($a < $aTarget || $b < $bTarget) {
            $guard++;
            if ($guard > 200) {
                $this->fail("feedSetScores stuck at a={$a} b={$b} (targets {$aTarget}/{$bTarget})");
            }

            $candidates = [];
            if ($a < $aTarget && ! $this->wouldCloseSet($a + 1, $b, $aTarget, $bTarget, $config)) {
                $candidates[] = 'A';
            }
            if ($b < $bTarget && ! $this->wouldCloseSet($a, $b + 1, $aTarget, $bTarget, $config)) {
                $candidates[] = 'B';
            }

            if ($candidates === []) {
                $this->fail("No safe next point at a={$a} b={$b} targets {$aTarget}/{$bTarget}");
            }

            $pick = $candidates[0];
            if (count($candidates) === 2) {
                $pick = ($aTarget - $a) >= ($bTarget - $b) ? 'A' : 'B';
            }

            if ($pick === 'A') {
                $this->service->recordPoint($match->refresh(), $this->teamA, $this->referee);
                $a++;
            } else {
                $this->service->recordPoint($match->refresh(), $this->teamB, $this->referee);
                $b++;
            }
        }
    }

    /**
     * Would (a, b) be a closed BWF set under the given config? Allow landing on
     * the intended target even if that target is itself a closing score.
     *
     * @param  array{pointsToWin:int,deuceCap:int}  $config
     */
    private function wouldCloseSet(int $a, int $b, int $aTarget, int $bTarget, array $config): bool
    {
        if ($a === $aTarget && $b === $bTarget) {
            return false;
        }
        $deuceCap = $config['deuceCap'];
        $pointsToWin = $config['pointsToWin'];
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

    private function setOf(MatchModel $match, int $setNumber): MatchSet
    {
        return MatchSet::query()
            ->where('match_id', $match->id)
            ->where('set_number', $setNumber)
            ->firstOrFail();
    }

    /**
     * a) GROUP match: A reaches 21 while B at 15. Single game to 21 with
     *    sets_to_win=1, so the match COMPLETES the instant set 1 closes.
     */
    public function test_group_match_completes_after_single_game_to_21(): void
    {
        $config = ['pointsToWin' => 21, 'deuceCap' => 30];
        $match = $this->startedMatch(MatchStage::GROUP);

        // Bring it to 20-15, then A scores the 21st point to close at 21-15.
        $this->feedSetScores($match, 20, 15, $config);
        $this->service->recordPoint($match->refresh(), $this->teamA, $this->referee);

        $set1 = $this->setOf($match, 1);
        $this->assertSame(21, $set1->team_a_score);
        $this->assertSame(15, $set1->team_b_score);
        $this->assertSame($this->teamA->id, $set1->winner_id, 'Set 1 should be won by Team A');
        $this->assertNotNull($set1->completed_at);

        $match->refresh();
        $this->assertSame(
            MatchStatus::COMPLETED,
            $match->status,
            'GROUP match (sets_to_win=1) must COMPLETE after one game to 21'
        );
        $this->assertSame($this->teamA->id, $match->winner_id);
        $this->assertNotNull($match->completed_at);

        // Single game: no second set was opened.
        $this->assertSame(1, MatchSet::query()->where('match_id', $match->id)->count());
    }

    /**
     * b) GROUP deuce: 20-20, then A -> 21-20 is NOT a win (need 2-point lead,
     *    cap 30 not reached). A scores again -> 22-20 -> won + COMPLETED.
     */
    public function test_group_deuce_requires_two_point_lead(): void
    {
        $config = ['pointsToWin' => 21, 'deuceCap' => 30];
        $match = $this->startedMatch(MatchStage::GROUP);

        $this->feedSetScores($match, 20, 20, $config);

        // 21-20: still a deuce, set stays open.
        $this->service->recordPoint($match->refresh(), $this->teamA, $this->referee);
        $set1 = $this->setOf($match, 1);
        $this->assertSame(21, $set1->team_a_score);
        $this->assertSame(20, $set1->team_b_score);
        $this->assertNull($set1->winner_id, '21-20 in deuce should NOT close the set (cap is 30)');
        $this->assertNull($set1->completed_at);

        $match->refresh();
        $this->assertSame(MatchStatus::IN_PROGRESS, $match->status);

        // 22-20: 2-point lead -> set closes -> match completes.
        $this->service->recordPoint($match->refresh(), $this->teamA, $this->referee);
        $set1 = $this->setOf($match, 1);
        $this->assertSame(22, $set1->team_a_score);
        $this->assertSame(20, $set1->team_b_score);
        $this->assertSame($this->teamA->id, $set1->winner_id, 'A wins set at 22-20');
        $this->assertNotNull($set1->completed_at);

        $match->refresh();
        $this->assertSame(MatchStatus::COMPLETED, $match->status);
        $this->assertSame($this->teamA->id, $match->winner_id);
    }

    /**
     * c) GROUP hard cap: 29-29, then A -> 30-29 wins at the cap (win-by-1 at
     *    cap 30) and the match COMPLETES.
     */
    public function test_group_hard_cap_wins_at_30_29(): void
    {
        $config = ['pointsToWin' => 21, 'deuceCap' => 30];
        $match = $this->startedMatch(MatchStage::GROUP);

        $this->feedSetScores($match, 29, 29, $config);
        $this->service->recordPoint($match->refresh(), $this->teamA, $this->referee);

        $set1 = $this->setOf($match, 1);
        $this->assertSame(30, $set1->team_a_score);
        $this->assertSame(29, $set1->team_b_score);
        $this->assertSame(
            $this->teamA->id,
            $set1->winner_id,
            'At deuce cap (30), a 1-point lead must close the set'
        );
        $this->assertNotNull($set1->completed_at);

        $match->refresh();
        $this->assertSame(MatchStatus::COMPLETED, $match->status);
        $this->assertSame($this->teamA->id, $match->winner_id);
    }

    /**
     * d) SEMIFINAL match in the SAME tournament: best of 3 to 15 (sets_to_win=2).
     *    Set 1 to A (15-10) does NOT complete the match; A then takes set 2 to
     *    complete. Proves knockout stages still use the main 15/2 config even
     *    though group overrides exist on the tournament.
     */
    public function test_semifinal_match_uses_knockout_best_of_three(): void
    {
        $config = ['pointsToWin' => 15, 'deuceCap' => 21];
        $match = $this->startedMatch(MatchStage::SEMIFINAL);

        // Set 1: A 15-10 -> set won, match NOT complete (needs 2 sets).
        $this->feedSetScores($match, 14, 10, $config);
        $this->service->recordPoint($match->refresh(), $this->teamA, $this->referee);

        $set1 = $this->setOf($match, 1);
        $this->assertSame(15, $set1->team_a_score);
        $this->assertSame(10, $set1->team_b_score);
        $this->assertSame($this->teamA->id, $set1->winner_id, 'A wins set 1 at 15-10');

        $match->refresh();
        $this->assertSame(
            MatchStatus::IN_PROGRESS,
            $match->status,
            'SEMIFINAL must NOT complete after one set (best of 3)'
        );

        // Set 2: A 15-9 -> second set won -> match completes.
        $this->feedSetScores($match, 14, 9, $config);
        $this->service->recordPoint($match->refresh(), $this->teamA, $this->referee);

        $set2 = $this->setOf($match, 2);
        $this->assertSame(15, $set2->team_a_score);
        $this->assertSame(9, $set2->team_b_score);
        $this->assertSame($this->teamA->id, $set2->winner_id, 'A wins set 2 at 15-9');

        $match->refresh();
        $this->assertSame(
            MatchStatus::COMPLETED,
            $match->status,
            'SEMIFINAL completes only after winning 2 sets'
        );
        $this->assertSame($this->teamA->id, $match->winner_id);
        $this->assertNotNull($match->completed_at);
    }
}
