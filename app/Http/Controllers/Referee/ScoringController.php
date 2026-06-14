<?php

declare(strict_types=1);

namespace App\Http\Controllers\Referee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Referee\ScorePointRequest;
use App\Http\Requests\Referee\WalkoverRequest;
use App\Models\MatchModel;
use App\Models\Team;
use App\Models\User;
use App\Services\Scoring\ScoringService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Inertia-friendly scoring actions. Each endpoint mutates state via
 * {@see ScoringService}, then returns a `redirect()->back()` so Inertia
 * treats it as a same-page POST and rehydrates the page props from the
 * originating Referee/Court or Referee/Dashboard view.
 *
 * Authorization: ADMIN bypasses court checks; REFEREE must have an active
 * RefereeAssignment for the match's court (and the match must have a court
 * assigned). Matches a 1:1 port of NestJS RefereesController.assertCanScore.
 */
class ScoringController extends Controller
{
    public function __construct(private readonly ScoringService $scoring)
    {
    }

    /**
     * POST /referee/matches/{match}/start
     *
     * Transitions a SCHEDULED match to IN_PROGRESS and seeds set 1.
     *
     * @throws AuthorizationException
     */
    public function start(Request $request, MatchModel $match): RedirectResponse
    {
        $this->assertCanScore($request->user(), $match);

        $this->scoring->startMatch($match);

        return redirect()->back();
    }

    /**
     * POST /referee/matches/{match}/points
     *
     * Records one point for the chosen team. Body: { scoring_team_id }.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function point(ScorePointRequest $request, MatchModel $match): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertCanScore($user, $match);

        /** @var Team $team */
        $team = Team::query()->findOrFail($request->scoringTeamId());

        $this->scoring->recordPoint($match, $team, $user);

        return redirect()->back();
    }

    /**
     * POST /referee/matches/{match}/undo
     *
     * Undoes the most recent point (flips event.undone, rebuilds sets).
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function undo(Request $request, MatchModel $match): RedirectResponse
    {
        $this->assertCanScore($request->user(), $match);

        $this->scoring->undoLastPoint($match);

        return redirect()->back();
    }

    /**
     * POST /referee/matches/{match}/walkover
     *
     * Declares a walkover for the given winner. Body: { winner_team_id }.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function walkover(WalkoverRequest $request, MatchModel $match): RedirectResponse
    {
        $this->assertCanScore($request->user(), $match);

        /** @var Team $winner */
        $winner = Team::query()->findOrFail($request->winnerTeamId());

        $this->scoring->declareWalkover($match, $winner);

        return redirect()->back();
    }

    /**
     * Referees choose their own court (no RefereeAssignment gate): any
     * active REFEREE or ADMIN may score any match that is on a court.
     * Role access is already enforced by the route middleware.
     *
     * @throws AuthorizationException
     */
    private function assertCanScore(?User $user, MatchModel $match): void
    {
        if ($user === null) {
            throw new AuthorizationException('Not authenticated');
        }

        if ($user->isAdmin()) {
            return;
        }

        if ($match->court_id === null) {
            throw new AuthorizationException('Match has no court assigned');
        }
    }
}
