<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MatchStage;
use App\Models\Court;
use App\Models\MatchModel;
use App\Models\Tournament;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ScheduleGroupMatches extends Command
{
    protected $signature = 'matches:schedule-group';

    protected $description = 'Idempotently assign scheduled_at + court_id to all GROUP-stage matches of "Sunday Club Mid-Year Tournament 2026" using a per-slot court-availability schedule.';

    /**
     * Calendar date of the group stage (Asia/Yangon wall-clock).
     */
    private const SLOT_DATE = '2026-06-21';

    private const TIMEZONE = 'Asia/Yangon';

    /**
     * Per-slot court availability for the group stage (Asia/Yangon).
     *
     * Each entry is a 30-minute slot with the number of courts open. When a
     * slot has N courts, the FIRST N courts ordered by display_order are used
     * (Court 1..N). Edit this list to change availability — the scheduling
     * logic reads it verbatim and needs no other changes.
     *
     * Total capacity below = 6+6+6+6 + 4+4+4+4 + 6+6+6+6 + 6+6+4+4 = 84 slots.
     * (16:00-17:30 = rest, 18:00 onward = knockout — neither is listed here.)
     *
     * @var list<array{time: string, courts: int}>
     */
    private const COURT_AVAILABILITY = [
        ['time' => '08:00', 'courts' => 6],
        ['time' => '08:30', 'courts' => 6],
        ['time' => '09:00', 'courts' => 6],
        ['time' => '09:30', 'courts' => 6],
        ['time' => '10:00', 'courts' => 4],
        ['time' => '10:30', 'courts' => 4],
        ['time' => '11:00', 'courts' => 4],
        ['time' => '11:30', 'courts' => 4],
        ['time' => '12:00', 'courts' => 6],
        ['time' => '12:30', 'courts' => 6],
        ['time' => '13:00', 'courts' => 6],
        ['time' => '13:30', 'courts' => 6],
        ['time' => '14:00', 'courts' => 6],
        ['time' => '14:30', 'courts' => 6],
        ['time' => '15:00', 'courts' => 4],
        ['time' => '15:30', 'courts' => 4],
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

        // Ordered active courts; the slot's first-N subset indexes into this.
        $courts = Court::query()
            ->forTournament($tournament->id)
            ->active()
            ->ordered()
            ->get()
            ->values();

        if ($courts->isEmpty()) {
            $this->error('No active courts found for tournament.');

            return self::FAILURE;
        }

        $maxCourtsNeeded = max(array_column(self::COURT_AVAILABILITY, 'courts'));
        if ($courts->count() < $maxCourtsNeeded) {
            $this->error(
                "Availability needs up to {$maxCourtsNeeded} courts but only {$courts->count()} active courts exist."
            );

            return self::FAILURE;
        }
        if ($courts->count() !== 6) {
            $this->warn("Expected 6 active courts, found {$courts->count()} — proceeding anyway.");
        }

        $totalCapacity = array_sum(array_column(self::COURT_AVAILABILITY, 'courts'));
        $this->info(
            'Availability: ' . count(self::COURT_AVAILABILITY) . ' slots, ' .
            "total capacity {$totalCapacity} match-slots."
        );

        /** @var \Illuminate\Database\Eloquent\Collection<int, MatchModel> $matches */
        $matches = MatchModel::query()
            ->where('tournament_id', $tournament->id)
            ->where('stage', MatchStage::GROUP->value)
            ->with(['teamA', 'teamB', 'group', 'category'])
            ->get();

        if ($matches->isEmpty()) {
            $this->error('No GROUP-stage matches found.');

            return self::FAILURE;
        }

        $this->info("Group matches to schedule: {$matches->count()}");

        // Resolve each team to its player IDs via the team_players pivot.
        $teamIds = $matches
            ->flatMap(fn (MatchModel $m): array => [$m->team_a_id, $m->team_b_id])
            ->unique()
            ->values();

        /** @var array<string, array<int, string>> $teamPlayers */
        $teamPlayers = DB::table('team_players')
            ->whereIn('team_id', $teamIds)
            ->get(['team_id', 'player_id'])
            ->groupBy('team_id')
            ->map(fn ($rows) => $rows->pluck('player_id')->all())
            ->all();

        /** @var array<string, array<int, string>> $matchPlayers match id => player ids */
        $matchPlayers = [];
        foreach ($matches as $m) {
            $matchPlayers[$m->id] = array_values(array_unique(array_merge(
                $teamPlayers[$m->team_a_id] ?? [],
                $teamPlayers[$m->team_b_id] ?? [],
            )));
        }

        // Precompute the UTC start instant of each availability slot so we can
        // persist scheduled_at and label rows in Yangon wall-clock.
        /** @var list<CarbonImmutable> $slotStartUtc */
        $slotStartUtc = [];
        foreach (self::COURT_AVAILABILITY as $entry) {
            $slotStartUtc[] = CarbonImmutable::parse(
                self::SLOT_DATE . ' ' . $entry['time'],
                self::TIMEZONE,
            )->utc();
        }

        // --- Greedy slot-by-slot assignment ----------------------------------
        /** @var array<string, int> $assignedSlot match id => slot index */
        $assignedSlot = [];
        /** @var array<string, string> $assignedCourt match id => court id */
        $assignedCourt = [];
        /** @var array<string, int> $playerLastSlot player id => most recent slot index */
        $playerLastSlot = [];
        /** @var array<string, int> $categoryRemaining category id => unscheduled count */
        $categoryRemaining = [];
        foreach ($matches as $m) {
            $categoryRemaining[$m->category_id] = ($categoryRemaining[$m->category_id] ?? 0) + 1;
        }

        $unscheduled = $matches->keyBy('id')->all();
        $backToBackCount = 0;
        $scheduleRows = [];
        /** @var array<int, int> $slotFill slot index => matches placed */
        $slotFill = [];
        $lastUsedSlot = -1;

        $slotCount = count(self::COURT_AVAILABILITY);
        for ($slot = 0; $slot < $slotCount && $unscheduled !== []; $slot++) {
            $courtsThisSlot = self::COURT_AVAILABILITY[$slot]['courts'];
            $slotFill[$slot] = 0;
            /** @var array<string, true> $busyPlayers players already playing this slot */
            $busyPlayers = [];

            // Use only the first N courts (ordered by display_order) for this slot.
            for ($c = 0; $c < $courtsThisSlot; $c++) {
                if ($unscheduled === []) {
                    break;
                }
                $court = $courts[$c];

                // Min unscheduled round per group (dynamic: matches assigned
                // earlier this slot already left the unscheduled pool, so a
                // round R+1 match may follow a round-R match at an equal slot).
                $groupMinRound = [];
                foreach ($unscheduled as $m) {
                    $r = (int) $m->round_number;
                    if (! isset($groupMinRound[$m->group_id]) || $r < $groupMinRound[$m->group_id]) {
                        $groupMinRound[$m->group_id] = $r;
                    }
                }

                $eligible = [];
                foreach ($unscheduled as $m) {
                    // HARD: loose round ordering within the group.
                    if ((int) $m->round_number !== $groupMinRound[$m->group_id]) {
                        continue;
                    }
                    // HARD: no player in two matches in the same slot.
                    foreach ($matchPlayers[$m->id] as $pid) {
                        if (isset($busyPlayers[$pid])) {
                            continue 2;
                        }
                    }
                    $eligible[] = $m;
                }

                if ($eligible === []) {
                    continue; // court stays empty this slot
                }

                // SOFT: prefer candidates whose players are all rested
                // (last played at slot <= current - 2, or never played).
                $rested = array_values(array_filter(
                    $eligible,
                    function (MatchModel $m) use ($matchPlayers, $playerLastSlot, $slot): bool {
                        foreach ($matchPlayers[$m->id] as $pid) {
                            if (isset($playerLastSlot[$pid]) && $playerLastSlot[$pid] > $slot - 2) {
                                return false;
                            }
                        }

                        return true;
                    },
                ));

                $pool = $rested !== [] ? $rested : $eligible;
                $relaxed = $rested === [];

                // SOFT: spread categories — prefer the category with the most
                // remaining unscheduled matches; tie-break deterministically.
                usort($pool, function (MatchModel $a, MatchModel $b) use ($categoryRemaining): int {
                    return [$categoryRemaining[$b->category_id], (int) $a->round_number, $a->id]
                        <=> [$categoryRemaining[$a->category_id], (int) $b->round_number, $b->id];
                });

                $pick = $pool[0];
                if ($relaxed) {
                    $backToBackCount++;
                }

                $assignedSlot[$pick->id] = $slot;
                $assignedCourt[$pick->id] = $court->id;
                unset($unscheduled[$pick->id]);
                $categoryRemaining[$pick->category_id]--;
                foreach ($matchPlayers[$pick->id] as $pid) {
                    $busyPlayers[$pid] = true;
                    $playerLastSlot[$pid] = $slot;
                }

                $slotTimeYangon = $slotStartUtc[$slot]->setTimezone(self::TIMEZONE);
                $scheduleRows[] = [
                    'slot' => $slot + 1,
                    'time' => $slotTimeYangon->format('D H:i'),
                    'courts_avail' => $courtsThisSlot,
                    'court' => $court->name,
                    'match' => "{$pick->teamA?->display_name} vs {$pick->teamB?->display_name}",
                    'category' => $this->shortCategoryName($pick->category?->name ?? '?'),
                ];
                $slotFill[$slot]++;
                $lastUsedSlot = $slot;
            }
        }

        if ($unscheduled !== []) {
            $this->newLine();
            $this->error(
                'Could not place all group matches within the ' . $slotCount .
                ' availability slots — ' . count($unscheduled) . ' left unscheduled:'
            );
            foreach ($unscheduled as $m) {
                $this->warn(sprintf(
                    '  [%s] %s vs %s (round %s, %s)',
                    $m->id,
                    $m->teamA?->display_name ?? '?',
                    $m->teamB?->display_name ?? '?',
                    $m->round_number,
                    $this->shortCategoryName($m->category?->name ?? '?'),
                ));
            }

            return self::FAILURE;
        }

        // --- Persist (idempotent: wipe then write inside one transaction) ----
        DB::transaction(function () use ($tournament, $assignedSlot, $assignedCourt, $slotStartUtc): void {
            MatchModel::query()
                ->where('tournament_id', $tournament->id)
                ->where('stage', MatchStage::GROUP->value)
                ->update(['scheduled_at' => null, 'court_id' => null]);

            foreach ($assignedSlot as $matchId => $slotIndex) {
                MatchModel::query()->whereKey($matchId)->update([
                    'scheduled_at' => $slotStartUtc[$slotIndex],
                    'court_id' => $assignedCourt[$matchId],
                ]);
            }
        });

        // --- Report -----------------------------------------------------------
        $this->newLine();
        $this->info('=== Per-slot fill (times in Asia/Yangon) ===');
        $fillRows = [];
        foreach (self::COURT_AVAILABILITY as $i => $entry) {
            $fillRows[] = [
                $i + 1,
                $entry['time'],
                $entry['courts'],
                $slotFill[$i] ?? 0,
            ];
        }
        $this->table(['Slot', 'Time', 'Courts', 'Placed'], $fillRows);

        $this->newLine();
        $this->info('=== Assignments (times in Asia/Yangon) ===');
        $this->table(
            ['Slot', 'Time', 'Avail', 'Court', 'Match', 'Category'],
            array_map(
                fn (array $r): array => [
                    $r['slot'], $r['time'], $r['courts_avail'], $r['court'], $r['match'], $r['category'],
                ],
                $scheduleRows,
            ),
        );

        $totalScheduled = count($assignedSlot);
        $totalSlots = $lastUsedSlot + 1;
        $lastSlotTime = $lastUsedSlot >= 0
            ? self::COURT_AVAILABILITY[$lastUsedSlot]['time']
            : '(none)';

        $this->newLine();
        $this->info("Total matches scheduled: {$totalScheduled}");
        $this->info("Total slots used: {$totalSlots}");
        $this->info("Last slot used (Yangon): {$lastSlotTime}");
        $this->info("Back-to-back relaxations (rest preference unmet): {$backToBackCount}");

        return self::SUCCESS;
    }

    /**
     * Compact category label: initials of words, keeping any parenthetical.
     */
    private function shortCategoryName(string $name): string
    {
        $paren = '';
        if (preg_match('/\(([^)]+)\)/', $name, $m)) {
            $paren = ' (' . implode('', array_map(
                static fn (string $w): string => mb_strtoupper(mb_substr($w, 0, 1)),
                preg_split('/\s+/', trim($m[1])) ?: [],
            )) . ')';
            $name = (string) preg_replace('/\([^)]*\)/', '', $name);
        }

        $initials = implode('', array_map(
            static fn (string $w): string => mb_strtoupper(mb_substr($w, 0, 1)),
            array_filter(preg_split('/[\s&]+/', trim($name)) ?: []),
        ));

        return $initials . $paren;
    }
}
