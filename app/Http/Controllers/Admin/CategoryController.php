<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CategoryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manage tournament categories (e.g. Men's Singles). Mirrors the canonical
 * {@see CourtController} CRUD shape.
 */
class CategoryController extends Controller
{
    /**
     * GET /manage/categories
     */
    public function index(): Response
    {
        $categories = Category::query()
            ->with('tournament:id,name')
            ->withCount(['teams', 'groups', 'matches'])
            ->select(['id', 'tournament_id', 'type', 'name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Category $c): array => $this->serialize($c))
            ->all();

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    /**
     * GET /manage/categories/create
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Categories/Form', [
            'category' => null,
            'tournaments' => $this->tournamentOptions(),
            'types' => CategoryType::labels(),
        ]);
    }

    /**
     * POST /manage/categories
     */
    public function store(CategoryRequest $request): RedirectResponse
    {
        Category::query()->create($request->validated());

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Category created.');
    }

    /**
     * GET /manage/categories/{category}/edit
     */
    public function edit(Category $category): Response
    {
        return Inertia::render('Admin/Categories/Form', [
            'category' => $this->serialize($category),
            'tournaments' => $this->tournamentOptions(),
            'types' => CategoryType::labels(),
        ]);
    }

    /**
     * PUT /manage/categories/{category}
     */
    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Category updated.');
    }

    /**
     * DELETE /manage/categories/{category}
     */
    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Category deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Category $c): array
    {
        return [
            'id' => $c->getKey(),
            'tournament_id' => $c->tournament_id,
            'tournamentName' => $c->tournament?->name,
            'type' => $c->type?->value,
            'name' => $c->name,
            'teamsCount' => (int) ($c->teams_count ?? 0),
            'groupsCount' => (int) ($c->groups_count ?? 0),
            'matchesCount' => (int) ($c->matches_count ?? 0),
        ];
    }

    /**
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
