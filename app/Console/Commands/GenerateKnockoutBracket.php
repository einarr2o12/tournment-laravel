<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MatchStage;
use App\Enums\MatchStatus;
use App\Models\Category;
use App\Models\Group;
use App\Models\MatchModel;
use App\Models\Tournament;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Generate the knockout bracket for "Sunday Club Mid-Year Tournament 2026".
 *
 * Confirmed format: every category gets exactly 4 knockout matches —
 *   SF1 (bracket_slot 1), SF2 (bracket_slot 2), FINAL (3), BRONZE (4).
 *
 * Seeding sources (group-letter-aware):
 *   - 2-group categories:
 *       SF1 = GroupA-1st vs GroupB-2nd  -> "G:A:1" / "G:B:2"
 *       SF2 = GroupB-1st vs GroupA-2nd  -> "G:B:1" / "G:A:2"
 *   - 1-group categories:
 *       SF1 = 1st vs 4th  -> "G::1" / "G::4"
 *       SF2 = 2nd vs 3rd  -> "G::2" / "G::3"
 *   - FINAL  = W:1 / W:2   (winners of SF1 / SF2)
 *   - BRONZE = L:1 / L:2   (losers of SF1 / SF2)
 *
 * Links: SF1 & SF2 next_match_id -> FINAL; loser_next_match_id -> BRONZE.
 *
 * Idempotent: deletes existing non-GROUP matches for the tournament first.
 */
class GenerateKnockoutBracket extends Command
{
    protected $signature = 'bracket:generate';

    protected $description = 'Idempotently (re)generate the 4-match-per-category knockout bracket (SF1, SF2, Final, Bronze) for "Sunday Club Mid-Year Tournament 2026".';

    private const TOURNAMENT_NAME = 'Sunday Club Mid-Year Tournament 2026';

    private const TIMEZONE = 'Asia/Yangon';

    /** Adjustable default kick-off times (Asia/Yangon wall-clock). */
    private const SEMI_AT = '2026-06-21 18:00';
    private const BRONZE_AT = '2026-06-21 20:00';
    private const FINAL_AT = '2026-06-21 20:30';

    public function handle(): int
    {
        $tournament = Tournament::query()->where('name', self::TOURNAMENT_NAME)->first();
        if (! $tournament) {
            $this->error('Tournament not found: ' . self::TOURNAMENT_NAME);

            return self::FAILURE;
        }

        $this->info("Tournament: {$tournament->name} ({$tournament->id})");

        $semiAt = CarbonImmutable::parse(self::SEMI_AT, self::TIMEZONE)->utc();
        $bronzeAt = CarbonImmutable::parse(self::BRONZE_AT, self::TIMEZONE)->utc();
        $finalAt = CarbonImmutable::parse(self::FINAL_AT, self::TIMEZONE)->utc();

        $categories = Category::query()
            ->where('tournament_id', $tournament->id)
            ->orderBy('name')
            ->get();

        if ($categories->isEmpty()) {
            $this->error('No categories found for tournament.');

            return self::FAILURE;
        }

        $created = 0;
        $summaryByCategory = [];

        DB::transaction(function () use (
            $tournament, $categories, $semiAt, $bronzeAt, $finalAt, &$created, &$summaryByCategory
        ): void {
            // Idempotent reset: drop every existing non-GROUP match.
            $deleted = MatchModel::query()
                ->where('tournament_id', $tournament->id)
                ->where('stage', '!=', MatchStage::GROUP->value)
                ->delete();
            $this->line("Cleared {$deleted} existing non-GROUP matches.");

            foreach ($categories as $category) {
                $groupCount = Group::query()->where('category_id', $category->id)->count();
                $sources = $this->seedingSources($groupCount);

                // Create Final (slot 3) and Bronze (slot 4) first so the
                // semifinals can point at them.
                $final = MatchModel::query()->create([
                    'tournament_id' => $tournament->id,
                    'category_id' => $category->id,
                    'stage' => MatchStage::FINAL->value,
                    'round_number' => 2,
                    'bracket_slot' => 3,
                    'status' => MatchStatus::SCHEDULED->value,
                    'team_a_source' => 'W:1',
                    'team_b_source' => 'W:2',
                    'scheduled_at' => $finalAt,
                ]);

                $bronze = MatchModel::query()->create([
                    'tournament_id' => $tournament->id,
                    'category_id' => $category->id,
                    'stage' => MatchStage::THIRD_PLACE->value,
                    'round_number' => 2,
                    'bracket_slot' => 4,
                    'status' => MatchStatus::SCHEDULED->value,
                    'team_a_source' => 'L:1',
                    'team_b_source' => 'L:2',
                    'scheduled_at' => $bronzeAt,
                ]);

                $sf1 = MatchModel::query()->create([
                    'tournament_id' => $tournament->id,
                    'category_id' => $category->id,
                    'stage' => MatchStage::SEMIFINAL->value,
                    'round_number' => 1,
                    'bracket_slot' => 1,
                    'status' => MatchStatus::SCHEDULED->value,
                    'team_a_source' => $sources['sf1_a'],
                    'team_b_source' => $sources['sf1_b'],
                    'next_match_id' => $final->id,
                    'loser_next_match_id' => $bronze->id,
                    'scheduled_at' => $semiAt,
                ]);

                $sf2 = MatchModel::query()->create([
                    'tournament_id' => $tournament->id,
                    'category_id' => $category->id,
                    'stage' => MatchStage::SEMIFINAL->value,
                    'round_number' => 1,
                    'bracket_slot' => 2,
                    'status' => MatchStatus::SCHEDULED->value,
                    'team_a_source' => $sources['sf2_a'],
                    'team_b_source' => $sources['sf2_b'],
                    'next_match_id' => $final->id,
                    'loser_next_match_id' => $bronze->id,
                    'scheduled_at' => $semiAt,
                ]);

                $created += 4;

                $summaryByCategory[] = [
                    'category' => $category->name,
                    'groups' => $groupCount,
                    'rows' => [
                        ['SF1', $sf1->id, $sf1->team_a_source, $sf1->team_b_source, 'win->FINAL  lose->BRONZE'],
                        ['SF2', $sf2->id, $sf2->team_a_source, $sf2->team_b_source, 'win->FINAL  lose->BRONZE'],
                        ['FINAL', $final->id, $final->team_a_source, $final->team_b_source, '-'],
                        ['BRONZE', $bronze->id, $bronze->team_a_source, $bronze->team_b_source, '-'],
                    ],
                ];
            }
        });

        // --- Report ---------------------------------------------------------
        foreach ($summaryByCategory as $cat) {
            $this->newLine();
            $this->info("=== {$cat['category']} ({$cat['groups']} group(s)) ===");
            $this->table(
                ['Match', 'Id', 'team_a_source', 'team_b_source', 'Links'],
                array_map(
                    static fn (array $r): array => [
                        $r[0],
                        substr((string) $r[1], 0, 8) . '…',
                        $r[2],
                        $r[3],
                        $r[4],
                    ],
                    $cat['rows'],
                ),
            );
        }

        $this->newLine();
        $this->info("Total knockout matches created: {$created} (expected " . (count($categories) * 4) . ').');

        return self::SUCCESS;
    }

    /**
     * Source strings for the two semifinals given a category's group count.
     *
     * @return array{sf1_a:string,sf1_b:string,sf2_a:string,sf2_b:string}
     */
    private function seedingSources(int $groupCount): array
    {
        if ($groupCount >= 2) {
            // SF1 = GroupA-1st vs GroupB-2nd; SF2 = GroupB-1st vs GroupA-2nd.
            return [
                'sf1_a' => 'G:A:1',
                'sf1_b' => 'G:B:2',
                'sf2_a' => 'G:B:1',
                'sf2_b' => 'G:A:2',
            ];
        }

        // Single group: SF1 = 1st vs 4th; SF2 = 2nd vs 3rd.
        return [
            'sf1_a' => 'G::1',
            'sf1_b' => 'G::4',
            'sf2_a' => 'G::2',
            'sf2_b' => 'G::3',
        ];
    }
}
