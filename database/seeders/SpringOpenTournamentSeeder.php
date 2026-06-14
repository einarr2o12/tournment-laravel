<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CategoryType;
use App\Enums\Gender;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Models\Category;
use App\Models\Court;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Mirrors the NestJS test fixture: Spring Open 2026 at City Sports Hall.
 *
 *   - 1 tournament
 *   - 3 courts (Court 1 / 2 / 3)
 *   - 5 categories (one of each type)
 *   - 22 players (12 male + 10 female), mixed Burmese + English names
 *   - 5 teams per category (25 teams total). Players are reused across
 *     categories — same human plays singles AND doubles, which is realistic.
 */
class SpringOpenTournamentSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $tournament = Tournament::updateOrCreate(
            ['name' => 'Spring Open 2026'],
            [
                'description' => 'Annual spring badminton open hosted at City Sports Hall.',
                'venue' => 'City Sports Hall',
                'format' => TournamentFormat::GROUP_KNOCKOUT->value,
                'status' => TournamentStatus::SCHEDULED->value,
                'points_to_win' => 21,
                'sets_to_win' => 2,
                'deuce_cap' => 30,
                'start_date' => now()->addDays(7)->startOfDay(),
                'end_date' => now()->addDays(9)->startOfDay(),
            ],
        );

        $this->seedCourts($tournament);
        $categories = $this->seedCategories($tournament);
        $players = $this->seedPlayers($tournament);
        $this->seedTeams($categories, $players);
    }

    /**
     * 3 courts numbered 1..3.
     */
    private function seedCourts(Tournament $tournament): void
    {
        foreach ([1, 2, 3] as $i) {
            Court::updateOrCreate(
                ['tournament_id' => $tournament->id, 'name' => 'Court '.$i],
                ['display_order' => $i, 'active' => true],
            );
        }
    }

    /**
     * One category for each CategoryType.
     *
     * @return array<string, Category>  keyed by CategoryType value
     */
    private function seedCategories(Tournament $tournament): array
    {
        $out = [];
        foreach (CategoryType::cases() as $type) {
            $out[$type->value] = Category::updateOrCreate(
                [
                    'tournament_id' => $tournament->id,
                    'type' => $type->value,
                    'name' => CategoryType::labels()[$type->value],
                ],
                [],
            );
        }

        return $out;
    }

    /**
     * 22 players — 12 male, 10 female. Names are a mix of Burmese (Romanised)
     * and English so search/UI gets exercised against both.
     *
     * @return array{male: list<Player>, female: list<Player>}
     */
    private function seedPlayers(Tournament $tournament): array
    {
        $males = [
            ['name' => 'Aung Ko Ko',     'club' => 'Yangon BC'],
            ['name' => 'Kyaw Thiha',      'club' => 'Yangon BC'],
            ['name' => 'Thant Zin Min',   'club' => 'Mandalay Smash'],
            ['name' => 'Soe Naing',       'club' => 'Mandalay Smash'],
            ['name' => 'Min Thu',         'club' => 'Naypyidaw Eagles'],
            ['name' => 'Zaw Win Htut',    'club' => 'Naypyidaw Eagles'],
            ['name' => 'Hein Htet Aung',  'club' => 'Bago Birds'],
            ['name' => 'Pyae Phyo Tun',   'club' => 'Bago Birds'],
            ['name' => 'Wai Yan Soe',     'club' => 'Mawlamyine Drive'],
            ['name' => 'David Chen',      'club' => 'Yangon BC'],
            ['name' => 'Michael Tan',     'club' => 'Mandalay Smash'],
            ['name' => 'James Wong',      'club' => 'Insein Aces'],
        ];

        $females = [
            ['name' => 'Su Hnin Aye',     'club' => 'Yangon BC'],
            ['name' => 'May Thu Win',     'club' => 'Yangon BC'],
            ['name' => 'Ei Phyu Phyu',    'club' => 'Mandalay Smash'],
            ['name' => 'Thiri Aung',      'club' => 'Mandalay Smash'],
            ['name' => 'Nilar Khin',      'club' => 'Naypyidaw Eagles'],
            ['name' => 'Yu Wai Aung',     'club' => 'Naypyidaw Eagles'],
            ['name' => 'Thinzar Oo',      'club' => 'Bago Birds'],
            ['name' => 'Cherry Hlaing',   'club' => 'Mawlamyine Drive'],
            ['name' => 'Sarah Lim',       'club' => 'Yangon BC'],
            ['name' => 'Emily Tan',       'club' => 'Insein Aces'],
        ];

        $maleModels = [];
        foreach ($males as $row) {
            $maleModels[] = Player::updateOrCreate(
                ['tournament_id' => $tournament->id, 'full_name' => $row['name']],
                ['gender' => Gender::MALE->value, 'club' => $row['club']],
            );
        }

        $femaleModels = [];
        foreach ($females as $row) {
            $femaleModels[] = Player::updateOrCreate(
                ['tournament_id' => $tournament->id, 'full_name' => $row['name']],
                ['gender' => Gender::FEMALE->value, 'club' => $row['club']],
            );
        }

        return ['male' => $maleModels, 'female' => $femaleModels];
    }

    /**
     * 5 teams per category. Players reused across categories.
     *
     * @param  array<string, Category>  $categories
     * @param  array{male: list<Player>, female: list<Player>}  $players
     */
    private function seedTeams(array $categories, array $players): void
    {
        $males = $players['male'];     // 12 male players
        $females = $players['female']; // 10 female players

        // ----- Men's Singles: 5 teams of 1 male each (first 5 males) -----
        $this->seedSinglesTeams(
            $categories[CategoryType::MENS_SINGLES->value],
            array_slice($males, 0, 5),
        );

        // ----- Women's Singles: 5 teams of 1 female each (first 5 females) -----
        $this->seedSinglesTeams(
            $categories[CategoryType::WOMENS_SINGLES->value],
            array_slice($females, 0, 5),
        );

        // ----- Men's Doubles: 5 teams × 2 males each — pair by club/position -----
        $this->seedDoublesTeams(
            $categories[CategoryType::MENS_DOUBLES->value],
            [
                [$males[0], $males[1]],   // Yangon BC pair
                [$males[2], $males[3]],   // Mandalay pair
                [$males[4], $males[5]],   // Naypyidaw pair
                [$males[6], $males[7]],   // Bago pair
                [$males[9], $males[10]],  // mixed clubs
            ],
        );

        // ----- Women's Doubles: 5 teams × 2 females each -----
        // Only 10 women available — every player slots in once.
        $this->seedDoublesTeams(
            $categories[CategoryType::WOMENS_DOUBLES->value],
            [
                [$females[0], $females[1]], // Yangon BC pair
                [$females[2], $females[3]], // Mandalay pair
                [$females[4], $females[5]], // Naypyidaw pair
                [$females[6], $females[7]], // mixed clubs
                [$females[8], $females[9]], // English-name pair
            ],
        );

        // ----- Mixed Doubles: 5 teams × (1 male, 1 female) -----
        $this->seedDoublesTeams(
            $categories[CategoryType::MIXED_DOUBLES->value],
            [
                [$males[0], $females[0]], // Yangon BC
                [$males[2], $females[2]], // Mandalay
                [$males[4], $females[4]], // Naypyidaw
                [$males[6], $females[6]], // Bago
                [$males[9], $females[8]], // English-name pair
            ],
        );
    }

    /**
     * @param  list<Player>  $singles
     */
    private function seedSinglesTeams(Category $category, array $singles): void
    {
        foreach ($singles as $index => $player) {
            $team = Team::updateOrCreate(
                [
                    'category_id' => $category->id,
                    'display_name' => $player->full_name,
                ],
                ['seed' => $index + 1],
            );

            // attach player at position 1 if not already
            $team->players()->syncWithoutDetaching([
                $player->id => ['position' => 1],
            ]);
        }
    }

    /**
     * @param  list<array{0: Player, 1: Player}>  $pairs
     */
    private function seedDoublesTeams(Category $category, array $pairs): void
    {
        foreach ($pairs as $index => [$p1, $p2]) {
            $displayName = $this->doublesDisplayName($p1, $p2);

            $team = Team::updateOrCreate(
                [
                    'category_id' => $category->id,
                    'display_name' => $displayName,
                ],
                ['seed' => $index + 1],
            );

            $team->players()->syncWithoutDetaching([
                $p1->id => ['position' => 1],
                $p2->id => ['position' => 2],
            ]);
        }
    }

    private function doublesDisplayName(Player $a, Player $b): string
    {
        // "Surname A / Surname B" — last word of each player's full name.
        $surname = static function (string $full): string {
            $parts = preg_split('/\s+/', trim($full)) ?: [$full];

            return $parts[count($parts) - 1];
        };

        return $surname($a->full_name).' / '.$surname($b->full_name);
    }
}
