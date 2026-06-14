<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MatchStage;
use App\Enums\MatchStatus;
use App\Models\Category;
use App\Models\Group;
use App\Models\MatchModel;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignGroupsAndScheduleRr extends Command
{
    protected $signature = 'groups:assign-and-schedule';

    protected $description = 'Idempotently rebuild groups + round-robin group-stage matches for "Sunday Club Mid-Year Tournament 2026".';

    /**
     * Per-category group spec: list of groups; each group is the list of seed numbers.
     *
     * @var array<string, array<int, array<int, int>>>
     */
    private array $spec = [
        "Senior Men's Doubles (Open Club)" => [
            'A' => [1, 4, 5, 8, 9],
            'B' => [2, 3, 6, 7],
        ],
        "Senior Men's & Mixed Doubles (Sunday Club)" => [
            'A' => [1, 4, 5, 8],
            'B' => [2, 3, 6, 7],
        ],
        "Intermediate Men's Doubles" => [
            'A' => [1, 2, 3, 4, 5, 6, 7],
        ],
        'Intermediate Mixed Doubles' => [
            'A' => [1, 2, 3, 4, 5],
        ],
        'Junior Level' => [
            'A' => [1, 2, 3, 4, 5, 6],
        ],
    ];

    public function handle(): int
    {
        $tournamentName = 'Sunday Club Mid-Year Tournament 2026';

        $tournament = Tournament::query()->where('name', $tournamentName)->first();
        if (! $tournament) {
            $this->error("Tournament not found: {$tournamentName}");

            return self::FAILURE;
        }

        $this->info("Tournament: {$tournament->name} ({$tournament->id})");

        $summary = [];
        $totalGroups = 0;
        $totalMatches = 0;

        DB::transaction(function () use ($tournament, &$summary, &$totalGroups, &$totalMatches): void {
            foreach ($tournament->categories()->orderBy('name')->get() as $category) {
                if (! array_key_exists($category->name, $this->spec)) {
                    $this->warn("  Skipping category (no spec): {$category->name}");

                    continue;
                }

                $catSpec = $this->spec[$category->name];

                // 1) Wipe existing GROUP-stage matches for this category (preserve knockout).
                $deletedMatches = MatchModel::query()
                    ->where('category_id', $category->id)
                    ->where('stage', MatchStage::GROUP->value)
                    ->delete();

                // 2) Wipe existing groups for the category (cascade clears group_teams pivot).
                $deletedGroups = Group::query()->where('category_id', $category->id)->delete();

                $this->line("[{$category->name}] wiped {$deletedGroups} group(s), {$deletedMatches} group-stage match(es)");

                // Load teams for the category by seed for quick lookup.
                $teamsBySeed = Team::query()
                    ->where('category_id', $category->id)
                    ->orderBy('seed')
                    ->get()
                    ->keyBy(fn (Team $t): int => (int) $t->seed);

                $catRows = [];
                $catMatchCount = 0;

                foreach ($catSpec as $letter => $seedList) {
                    $group = Group::factory()
                        ->forCategory($category)
                        ->named("Group {$letter}")
                        ->create();

                    /** @var array<int, Team> $teamsInGroup */
                    $teamsInGroup = [];
                    foreach ($seedList as $seed) {
                        if (! $teamsBySeed->has($seed)) {
                            throw new \RuntimeException(
                                "Missing team with seed {$seed} in category '{$category->name}'."
                            );
                        }
                        $teamsInGroup[] = $teamsBySeed[$seed];
                    }

                    // Attach teams to group via pivot.
                    $group->teams()->attach(array_map(static fn (Team $t): string => $t->id, $teamsInGroup));

                    // Build round-robin schedule using the circle method.
                    $matches = $this->buildRoundRobin($teamsInGroup);

                    foreach ($matches as $m) {
                        /** @var Team $teamLow */
                        $teamLow = $m['team_a'];
                        /** @var Team $teamHigh */
                        $teamHigh = $m['team_b'];

                        MatchModel::query()->create([
                            'tournament_id' => $tournament->id,
                            'category_id' => $category->id,
                            'court_id' => null,
                            'group_id' => $group->id,
                            'stage' => MatchStage::GROUP,
                            'round_number' => $m['round_number'],
                            'bracket_slot' => null,
                            'next_match_id' => null,
                            'scheduled_at' => null,
                            'started_at' => null,
                            'completed_at' => null,
                            'status' => MatchStatus::SCHEDULED,
                            'team_a_id' => $teamLow->id,
                            'team_b_id' => $teamHigh->id,
                            'winner_id' => null,
                            'notes' => null,
                        ]);
                        $catMatchCount++;
                    }

                    $catRows[] = [
                        'category' => $category->name,
                        'group' => "Group {$letter}",
                        'teams' => count($teamsInGroup),
                        'matches' => count($matches),
                    ];
                    $totalGroups++;
                }

                $totalMatches += $catMatchCount;
                $summary = array_merge($summary, $catRows);
            }
        });

        $this->newLine();
        $this->info('=== Summary ===');
        $this->table(
            ['Category', 'Group', 'Teams', 'Matches'],
            array_map(fn (array $r): array => [$r['category'], $r['group'], $r['teams'], $r['matches']], $summary),
        );

        $this->info("Totals: {$totalGroups} group(s), {$totalMatches} match(es) across all categories.");

        return self::SUCCESS;
    }

    /**
     * Build a round-robin schedule using the circle method.
     *
     * Pair ordering for each match: team_a = lower seed, team_b = higher seed.
     * Returns: list of ['team_a' => Team, 'team_b' => Team, 'round_number' => int].
     *
     * @param  array<int, Team>  $teams  ordered by seed ascending
     * @return array<int, array{team_a: Team, team_b: Team, round_number: int}>
     */
    private function buildRoundRobin(array $teams): array
    {
        $n = count($teams);
        if ($n < 2) {
            return [];
        }

        // Add a "bye" placeholder if odd.
        $items = $teams;
        $hasBye = false;
        if ($n % 2 === 1) {
            $items[] = null; // bye
            $hasBye = true;
        }
        $m = count($items); // even
        $rounds = $m - 1;
        $half = intdiv($m, 2);

        // Circle method: index 0 fixed, others rotate.
        // Use 0..m-1 as positions; positions[0] is always the same team; positions[1..m-1] rotate.
        $positions = range(0, $m - 1);

        $schedule = [];

        for ($r = 1; $r <= $rounds; $r++) {
            for ($i = 0; $i < $half; $i++) {
                $aIdx = $positions[$i];
                $bIdx = $positions[$m - 1 - $i];
                $a = $items[$aIdx];
                $b = $items[$bIdx];

                if ($hasBye && ($a === null || $b === null)) {
                    continue; // skip bye match this round
                }

                // Order pair so team_a is lower seed.
                /** @var Team $a */
                /** @var Team $b */
                if ((int) $a->seed > (int) $b->seed) {
                    [$a, $b] = [$b, $a];
                }

                $schedule[] = [
                    'team_a' => $a,
                    'team_b' => $b,
                    'round_number' => $r,
                ];
            }

            // Rotate: keep positions[0] fixed, rotate positions[1..m-1] clockwise.
            $fixed = $positions[0];
            $rest = array_slice($positions, 1);
            // Clockwise rotation: take the last element of $rest and move it to the front.
            $last = array_pop($rest);
            array_unshift($rest, $last);
            $positions = array_merge([$fixed], $rest);
        }

        return $schedule;
    }
}
