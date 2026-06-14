<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CourtRequest;
use App\Models\Court;
use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CANONICAL ADMIN CRUD CONTROLLER — the parallel phase clones this shape for
 * the other resources. Conventions to mirror:
 *   - index() lists + serializes via a private serialize() helper.
 *   - create()/edit() render the SAME Form.vue page (mode inferred from the
 *     presence of the `court` prop).
 *   - store()/update() validate through a FormRequest, then redirect back to
 *     index with a flash message so Inertia rehydrates props.
 *   - destroy() deletes and redirects to index.
 */
class CourtController extends Controller
{
    /**
     * GET /manage/courts
     */
    public function index(): Response
    {
        $courts = Court::query()
            ->with('tournament:id,name')
            ->select(['id', 'tournament_id', 'name', 'display_order', 'active'])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Court $c): array => $this->serialize($c))
            ->all();

        return Inertia::render('Admin/Courts/Index', [
            'courts' => $courts,
        ]);
    }

    /**
     * GET /manage/courts/create
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Courts/Form', [
            'court' => null,
            'tournaments' => $this->tournamentOptions(),
        ]);
    }

    /**
     * POST /manage/courts
     */
    public function store(CourtRequest $request): RedirectResponse
    {
        Court::query()->create($request->validated());

        return redirect()
            ->route('admin.courts.index')
            ->with('success', 'Court created.');
    }

    /**
     * GET /manage/courts/{court}/edit
     */
    public function edit(Court $court): Response
    {
        return Inertia::render('Admin/Courts/Form', [
            'court' => $this->serialize($court),
            'tournaments' => $this->tournamentOptions(),
        ]);
    }

    /**
     * PUT /manage/courts/{court}
     */
    public function update(CourtRequest $request, Court $court): RedirectResponse
    {
        $court->update($request->validated());

        return redirect()
            ->route('admin.courts.index')
            ->with('success', 'Court updated.');
    }

    /**
     * DELETE /manage/courts/{court}
     */
    public function destroy(Court $court): RedirectResponse
    {
        $court->delete();

        return redirect()
            ->route('admin.courts.index')
            ->with('success', 'Court deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Court $c): array
    {
        return [
            'id' => $c->getKey(),
            'tournament_id' => $c->tournament_id,
            'tournamentName' => $c->tournament?->name,
            'name' => $c->name,
            'display_order' => (int) $c->display_order,
            'active' => (bool) $c->active,
        ];
    }

    /**
     * Select options for the tournament dropdown on the form.
     *
     * @return list<array<string, string>>
     */
    private function tournamentOptions(): array
    {
        return Tournament::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Tournament $t): array => [
                'id' => $t->getKey(),
                'name' => $t->name,
            ])
            ->all();
    }
}
