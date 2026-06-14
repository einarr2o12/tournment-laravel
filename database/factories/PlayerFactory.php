<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Gender;
use App\Models\Player;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    protected $model = Player::class;

    /**
     * Burmese male given names (Romanised).
     *
     * @var list<string>
     */
    private const MALE_FIRST = [
        'Aung', 'Kyaw', 'Thant', 'Soe', 'Min', 'Zaw', 'Htet', 'Naing', 'Nyein', 'Pyae',
        'Hein', 'Wai', 'Yan', 'Ko', 'Win', 'Myo', 'Phyo', 'Tun', 'Maung', 'Thaung',
    ];

    /**
     * Burmese female given names (Romanised).
     *
     * @var list<string>
     */
    private const FEMALE_FIRST = [
        'Su', 'Hnin', 'Ei', 'Thiri', 'Nilar', 'May', 'Yu', 'Khin', 'Mya', 'Phyu',
        'Thinzar', 'Wai Wai', 'Aye', 'Nway', 'Sandar', 'Moe', 'Honey', 'Thazin', 'Pwint', 'Cherry',
    ];

    /**
     * Burmese family-style suffixes (Romanised).
     *
     * @var list<string>
     */
    private const BURMESE_LAST = [
        'Aung', 'Hein', 'Kyaw', 'Lin', 'Min', 'Moe', 'Naing', 'Oo', 'Phyo', 'San',
        'Soe', 'Thant', 'Tun', 'Win', 'Zaw', 'Htun', 'Myint', 'Nyunt', 'Ko Ko',
    ];

    /**
     * Burmese badminton clubs.
     *
     * @var list<string>
     */
    private const CLUBS = [
        'Yangon BC',
        'Mandalay Smash',
        'Naypyidaw Eagles',
        'Bago Birds',
        'Mawlamyine Drive',
        'Taunggyi Highlanders',
        'Pathein Shuttlers',
        'Monywa Net Kings',
        'Sittwe Wave',
        'Pyay Power BC',
        'Insein Aces',
        'Hlaing Tharyar Strikers',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement(Gender::cases());

        return [
            'tournament_id' => Tournament::factory(),
            'full_name' => $this->generateName($gender),
            'gender' => $gender->value,
            'club' => fake()->optional(0.85)->randomElement(self::CLUBS),
            'contact' => fake()->optional(0.6)->phoneNumber(),
        ];
    }

    public function male(): static
    {
        return $this->state(fn (): array => [
            'gender' => Gender::MALE->value,
            'full_name' => $this->generateName(Gender::MALE),
        ]);
    }

    public function female(): static
    {
        return $this->state(fn (): array => [
            'gender' => Gender::FEMALE->value,
            'full_name' => $this->generateName(Gender::FEMALE),
        ]);
    }

    public function forTournament(Tournament|string $tournament): static
    {
        $id = $tournament instanceof Tournament ? $tournament->id : $tournament;

        return $this->state(fn (): array => [
            'tournament_id' => $id,
        ]);
    }

    public function fromClub(string $club): static
    {
        return $this->state(fn (): array => [
            'club' => $club,
        ]);
    }

    /**
     * Mix Burmese-style names with a sprinkling of English names so admin
     * search/filter UI gets exercised against both.
     */
    private function generateName(Gender $gender): string
    {
        // 75% Burmese-style names, 25% English-style names.
        if (fake()->boolean(75)) {
            $first = $gender === Gender::MALE
                ? fake()->randomElement(self::MALE_FIRST)
                : fake()->randomElement(self::FEMALE_FIRST);
            $last = fake()->randomElement(self::BURMESE_LAST);

            return $first.' '.$last;
        }

        return $gender === Gender::MALE
            ? fake()->firstNameMale().' '.fake()->lastName()
            : fake()->firstNameFemale().' '.fake()->lastName();
    }
}
