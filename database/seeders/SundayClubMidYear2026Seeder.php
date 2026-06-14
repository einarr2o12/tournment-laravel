<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CategoryType;
use App\Enums\Gender;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Models\Category;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sunday Club Mid-Year Tournament 2026 (single-day, 2026-06-21).
 *
 *   - 1 tournament at "Sunday Club"
 *   - 5 categories (Senior MD, Senior Mixed, Intermediate MD, Intermediate Mixed,
 *     Junior MD)
 *   - Players deduped by case-insensitive trimmed full_name
 *   - Idempotent: existing tournament with the same name is purged and recreated
 *     inside a single DB transaction.
 */
class SundayClubMidYear2026Seeder extends Seeder
{
    use WithoutModelEvents;

    private const TOURNAMENT_NAME = 'Sunday Club Mid-Year Tournament 2026';

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->purgeExisting();

            $tournament = Tournament::updateOrCreate(
                ['name' => self::TOURNAMENT_NAME],
                [
                    'description' => 'Sunday Club single-day mid-year tournament — 5 categories.',
                    'venue' => 'Sunday Club',
                    'format' => TournamentFormat::GROUP_KNOCKOUT->value,
                    'status' => TournamentStatus::SCHEDULED->value,
                    'points_to_win' => 21,
                    'sets_to_win' => 2,
                    'deuce_cap' => 30,
                    'start_date' => Carbon::parse('2026-06-21')->startOfDay(),
                    'end_date' => Carbon::parse('2026-06-21')->startOfDay(),
                ],
            );

            $this->seedAll($tournament);
        });
    }

    /**
     * Wipe any prior copy of this tournament so a re-run produces clean data.
     * Cascades to categories/teams/team_players via FKs; players are scoped by
     * tournament_id so we delete them explicitly.
     */
    private function purgeExisting(): void
    {
        $existing = Tournament::where('name', self::TOURNAMENT_NAME)->first();
        if ($existing === null) {
            return;
        }

        // Wipe players tied to this tournament; team_players pivot will cascade.
        Player::where('tournament_id', $existing->id)->delete();
        // Categories cascade to teams, teams cascade to team_players.
        Category::where('tournament_id', $existing->id)->delete();
        $existing->delete();
    }

    private function seedAll(Tournament $tournament): void
    {
        // Player cache: case-insensitive trimmed name → Player model.
        // Ensures "Zwe Htet" / "Po Htet" (seen in cat 1 and cat 2) are one row.
        /** @var array<string, Player> $playerCache */
        $playerCache = [];

        $categories = $this->categorySpecs();

        foreach ($categories as $spec) {
            $category = Category::updateOrCreate(
                [
                    'tournament_id' => $tournament->id,
                    'type' => $spec['type']->value,
                    'name' => $spec['name'],
                ],
                [],
            );

            foreach ($spec['teams'] as $index => $pair) {
                [$nameA, $nameB] = $pair;

                $playerA = $this->getOrCreatePlayer($tournament, $playerCache, $nameA, $spec['type'], 0);
                $playerB = $this->getOrCreatePlayer($tournament, $playerCache, $nameB, $spec['type'], 1);

                $displayName = $playerA->full_name.' + '.$playerB->full_name;

                $team = Team::updateOrCreate(
                    [
                        'category_id' => $category->id,
                        'display_name' => $displayName,
                    ],
                    ['seed' => $index + 1],
                );

                $team->players()->syncWithoutDetaching([
                    $playerA->id => ['position' => 1],
                    $playerB->id => ['position' => 2],
                ]);
            }
        }
    }

    /**
     * Resolve a player by case-insensitive trimmed name, creating once. Gender
     * is decided by category type + slot position (mixed: slot 0 male, slot 1
     * female). Subsequent appearances reuse the first-created row.
     *
     * @param  array<string, Player>  $cache  passed by reference
     */
    private function getOrCreatePlayer(
        Tournament $tournament,
        array &$cache,
        string $rawName,
        CategoryType $categoryType,
        int $slot,
    ): Player {
        $name = trim($rawName);
        $key = mb_strtolower($name);

        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $gender = match ($categoryType) {
            CategoryType::WOMENS_SINGLES, CategoryType::WOMENS_DOUBLES => Gender::FEMALE,
            CategoryType::MIXED_DOUBLES => $slot === 1 ? Gender::FEMALE : Gender::MALE,
            default => Gender::MALE, // MENS_*, Junior MD
        };

        $player = Player::updateOrCreate(
            ['tournament_id' => $tournament->id, 'full_name' => $name],
            ['gender' => $gender->value, 'club' => null],
        );

        $cache[$key] = $player;

        return $player;
    }

    /**
     * The five categories and their teams in display order. Teams listed as
     * [playerA, playerB] in seed order (index + 1 == seed).
     *
     * Intentionally skipped per spec:
     *   - Intermediate Mixed Doubles: "Kai + ?" (no partner)
     *   - Intermediate Mixed Doubles: solo "William" (no partner)
     *
     * @return list<array{type: CategoryType, name: string, teams: list<array{0: string, 1: string}>}>
     */
    private function categorySpecs(): array
    {
        return [
            [
                'type' => CategoryType::MENS_DOUBLES,
                'name' => "Senior Men's Doubles (Open Club)",
                'teams' => [
                    ['Patrick', 'Asaph'],
                    ['Pudge', 'Tun Htet'],
                    ['Bhone Latt Yone', 'Yan Kyaw'],
                    ['Leo', 'U Kaung'],
                    ['Jimmy', 'Simon'],
                    ['Bank', 'Richard'],
                    ['Zwe Htet', 'Po Htet'],
                    ['Chan Naing Kha', 'Aung Bhone'],
                    ['Eiji', 'Kyaw G'],
                ],
            ],
            [
                'type' => CategoryType::MIXED_DOUBLES,
                'name' => "Senior Men's & Mixed Doubles (Sunday Club)",
                'teams' => [
                    ['Luca', 'KWY'],
                    ['WHA', 'AMO'],
                    ['MK', 'SW'],
                    ['Zaw', 'KTL'],
                    ['Zwe Htet', 'Po Htet'], // reuses Player rows from category 1
                    ['Su Htoo', 'Albert'],
                    ['Sabai', 'Tkay'],
                    ['Osborn', 'Toby Won'],
                ],
            ],
            [
                'type' => CategoryType::MENS_DOUBLES,
                'name' => "Intermediate Men's Doubles",
                'teams' => [
                    ['Zay', 'Ein'],
                    ['Phone Pyae', 'Kyaw'],
                    ['ZNM', 'Kaung'],
                    ['TP', 'AKK'],
                    ['William', 'John'],
                    ['CK', 'Luke'],
                    ['Sam', 'Shady'],
                ],
            ],
            [
                'type' => CategoryType::MIXED_DOUBLES,
                'name' => 'Intermediate Mixed Doubles',
                'teams' => [
                    ['Maxz', 'Nyein'],
                    // "Kai + ?" skipped — no partner
                    ['AKM', 'Liz'],
                    ['Nway', 'Ko Htoo'],
                    ['Sai', 'Hnin'],
                    // solo "William" skipped — no partner
                ],
            ],
            [
                'type' => CategoryType::MENS_DOUBLES,
                'name' => 'Junior Level',
                'teams' => [
                    ['Zaw Zaw Lwin', 'Aye Chan'],
                    ['Myat Min Ko', 'Apa'],
                    ['Thu Rein', 'Swe'],
                    ['Zak', 'Mo'],
                    ['Aron', 'Tori'],
                ],
            ],
        ];
    }
}
