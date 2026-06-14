<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tournament>
 */
class TournamentFactory extends Factory
{
    protected $model = Tournament::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cities = [
            'Yangon', 'Mandalay', 'Naypyidaw', 'Bago', 'Mawlamyine',
            'Taunggyi', 'Pathein', 'Monywa', 'Sittwe', 'Pyay',
        ];
        $seasons = ['Spring', 'Summer', 'Autumn', 'Winter', 'New Year', 'Independence', 'Thingyan'];
        $events = ['Open', 'Cup', 'Championship', 'Invitational', 'Masters', 'Classic'];

        $city = fake()->randomElement($cities);
        $season = fake()->randomElement($seasons);
        $event = fake()->randomElement($events);
        $year = (int) date('Y');

        $venues = [
            'City Sports Hall',
            'National Indoor Stadium',
            'Aung San Stadium',
            'Thuwunna Indoor Stadium',
            'Theinbyu Sports Complex',
            'Yangon Sports Arena',
        ];

        $start = fake()->dateTimeBetween('-3 months', '+3 months');
        $end = (clone $start)->modify('+'.fake()->numberBetween(1, 4).' days');

        return [
            'name' => sprintf('%s %s %s %d', $city, $season, $event, $year),
            'description' => fake()->optional()->sentence(12),
            'venue' => fake()->randomElement($venues),
            'format' => TournamentFormat::GROUP_KNOCKOUT->value,
            'status' => TournamentStatus::DRAFT->value,
            'points_to_win' => 21,
            'sets_to_win' => 2,
            'deuce_cap' => 30,
            'start_date' => $start,
            'end_date' => $end,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => TournamentStatus::DRAFT->value,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'status' => TournamentStatus::SCHEDULED->value,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (): array => [
            'status' => TournamentStatus::IN_PROGRESS->value,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => TournamentStatus::COMPLETED->value,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => TournamentStatus::ARCHIVED->value,
        ]);
    }

    public function format(TournamentFormat $format): static
    {
        return $this->state(fn (): array => [
            'format' => $format->value,
        ]);
    }
}
