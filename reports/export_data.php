<?php

// Run from project root inside the container:
//   docker compose exec -T laravel.test sh -c "php reports/export_data.php"
// Outputs reports/groups_data.json + reports/schedule_data.json

use App\Models\Tournament;
use App\Models\MatchModel;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$t = Tournament::with(['categories.groups.teams.players'])->first();

$groups = [];
foreach ($t->categories->sortBy('name') as $cat) {
    foreach ($cat->groups->sortBy('name') as $g) {
        foreach ($g->teams->sortBy('seed') as $team) {
            $players = $team->players->sortBy(fn ($p) => $p->pivot->position)->values();
            $groups[] = [
                'category' => $cat->name,
                'group' => $g->name,
                'seed' => (int) $team->seed,
                'team' => $team->display_name,
                'player1' => $players[0]->full_name ?? '',
                'player2' => $players[1]->full_name ?? '',
            ];
        }
    }
}

$schedule = [];
$matches = MatchModel::with(['category', 'group', 'court', 'teamA', 'teamB'])
    ->where('stage', 'GROUP')
    ->whereNotNull('scheduled_at')
    ->orderBy('scheduled_at')
    ->orderBy('court_id')
    ->get();
foreach ($matches as $m) {
    $y = $m->scheduled_at->copy()->setTimezone('Asia/Yangon');
    $schedule[] = [
        'date' => $y->format('D, M j'),
        'time' => $y->format('g:i A'),
        'court' => $m->court?->name ?? '',
        'category' => $m->category?->name ?? '',
        'group' => $m->group?->name ?? '',
        'round' => (int) $m->round_number,
        'teamA' => $m->teamA?->display_name ?? 'TBD',
        'teamB' => $m->teamB?->display_name ?? 'TBD',
        'status' => $m->status->value,
    ];
}

file_put_contents(__DIR__.'/groups_data.json', json_encode(['tournament' => $t->name, 'venue' => $t->venue, 'rows' => $groups], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents(__DIR__.'/schedule_data.json', json_encode(['tournament' => $t->name, 'venue' => $t->venue, 'rows' => $schedule], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo 'groups: '.count($groups).' | schedule: '.count($schedule).PHP_EOL;
