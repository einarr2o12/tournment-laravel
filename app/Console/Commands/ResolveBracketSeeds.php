<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Tournament;
use App\Services\BracketSeeder;
use Illuminate\Console\Command;

/**
 * Resolve the "G:*" knockout seed sources into concrete team_a_id/team_b_id
 * once a category's group stage is fully decided.
 *
 * The per-category resolution logic now lives in {@see BracketSeeder} so the
 * same code runs both here (standalone, across the whole tournament) and inline
 * after an admin completes the final group match of a category.
 *
 * Idempotent and safe to re-run: only null slots whose source is "G:*" are
 * written, so re-running after some semifinals have started will not clobber
 * results.
 */
class ResolveBracketSeeds extends Command
{
    protected $signature = 'bracket:resolve';

    protected $description = 'Resolve "G:*" knockout seed sources to actual teams for each category whose group stage is complete, in "Sunday Club Mid-Year Tournament 2026".';

    private const TOURNAMENT_NAME = 'Sunday Club Mid-Year Tournament 2026';

    public function handle(BracketSeeder $seeder): int
    {
        $tournament = Tournament::query()->where('name', self::TOURNAMENT_NAME)->first();
        if (! $tournament) {
            $this->error('Tournament not found: ' . self::TOURNAMENT_NAME);

            return self::FAILURE;
        }

        $this->info("Tournament: {$tournament->name} ({$tournament->id})");

        $categories = Category::query()
            ->where('tournament_id', $tournament->id)
            ->orderBy('name')
            ->get();

        $totalFilled = 0;

        foreach ($categories as $category) {
            if (! $seeder->isGroupStageComplete($category)) {
                $this->warn("group stage not complete for {$category->name}");

                continue;
            }

            $filled = $seeder->resolveForCategory($category);
            $totalFilled += $filled;

            if ($filled === 0) {
                $this->line("{$category->name}: already resolved (no null G:* slots).");
            } else {
                $this->info("{$category->name}: filled {$filled} slot(s).");
            }
        }

        $this->newLine();
        $this->info("Total slots resolved: {$totalFilled}");

        return self::SUCCESS;
    }
}
