<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only view of round-robin groups. Groups (and their team membership)
 * are produced by the draw engine, so this admin surface is list-only.
 */
class GroupController extends Controller
{
    /**
     * GET /manage/groups
     */
    public function index(): Response
    {
        $groups = Group::query()
            ->with([
                'category:id,tournament_id,name,type',
                'category.tournament:id,name',
                'teams' => fn ($q) => $q->select(['teams.id', 'teams.display_name', 'teams.seed']),
            ])
            ->withCount('matches')
            ->select(['id', 'category_id', 'name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Group $g): array => $this->serialize($g))
            ->all();

        return Inertia::render('Admin/Groups/Index', [
            'groups' => $groups,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Group $g): array
    {
        return [
            'id' => $g->getKey(),
            'category_id' => $g->category_id,
            'categoryName' => $g->category?->name,
            'tournamentName' => $g->category?->tournament?->name,
            'name' => $g->name,
            'matchesCount' => (int) ($g->matches_count ?? 0),
            'teams' => $g->teams->map(fn ($t): array => [
                'id' => $t->getKey(),
                'displayName' => $t->display_name ?? '',
                'seed' => $t->seed,
            ])->values()->all(),
        ];
    }
}
