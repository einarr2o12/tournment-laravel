# Laravel Migration Plan

This document describes the migration from the existing Prisma/NestJS schema to a Laravel-native migration set. It covers enums, tables (with fields, indexes, and relations), and porting notes.

---

## Enums

Laravel migrations declare these via `$table->enum(...)`. On Postgres this produces a `varchar` with a `CHECK` constraint, which keeps the schema portable to MySQL/SQLite. Values mirror the Prisma definitions exactly (UPPER_SNAKE_CASE) so existing `ScoringConfig` and DTOs continue to work after the port.

### UserRole
- `ADMIN`
- `REFEREE`

### TournamentStatus
- `DRAFT`
- `SCHEDULED`
- `IN_PROGRESS`
- `COMPLETED`
- `ARCHIVED`

### TournamentFormat
- `SINGLE_ELIMINATION`
- `ROUND_ROBIN`
- `GROUP_KNOCKOUT`
- `SWISS`

### CategoryType
- `MENS_SINGLES`
- `WOMENS_SINGLES`
- `MENS_DOUBLES`
- `WOMENS_DOUBLES`
- `MIXED_DOUBLES`

### Gender
- `MALE`
- `FEMALE`

### MatchStage
- `GROUP`
- `ROUND_OF_64`
- `ROUND_OF_32`
- `ROUND_OF_16`
- `QUARTERFINAL`
- `SEMIFINAL`
- `FINAL`
- `THIRD_PLACE`

### MatchStatus
- `SCHEDULED`
- `IN_PROGRESS`
- `COMPLETED`
- `WALKOVER`
- `CANCELLED`

---

## Tables

Migrations are listed in dependency order. Each section names the migration file, the Eloquent model, the table, the schema fields, indexes/constraints, and the relations.

### 1. `2026_06_11_120000_create_users_table`
- **Model:** `User`
- **Table:** `users`

**Fields**
```php
$table->uuid('id')->primary();
$table->string('username', 80)->unique();
$table->string('password_hash');
$table->string('full_name', 160)->nullable();
$table->enum('role', ['ADMIN', 'REFEREE']);
$table->boolean('active')->default(true);
$table->timestamp('last_login_at')->nullable();
$table->timestamps();
```

**Indexes**
```php
$table->index('role');
```

**Relations**
- `hasMany`: `RefereeAssignment` via `user_id`
- `hasMany`: `ScoreEvent` via `scored_by_user_id` (nullable)

---

### 2. `2026_06_11_120001_create_tournaments_table`
- **Model:** `Tournament`
- **Table:** `tournaments`

**Fields**
```php
$table->uuid('id')->primary();
$table->string('name', 200);
$table->text('description')->nullable();
$table->string('venue', 200)->nullable();
$table->enum('format', ['SINGLE_ELIMINATION', 'ROUND_ROBIN', 'GROUP_KNOCKOUT', 'SWISS'])->default('GROUP_KNOCKOUT');
$table->enum('status', ['DRAFT', 'SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'ARCHIVED'])->default('DRAFT');
$table->unsignedSmallInteger('points_to_win')->default(21);
$table->unsignedSmallInteger('sets_to_win')->default(2);
$table->unsignedSmallInteger('deuce_cap')->default(30);
$table->timestamp('start_date')->nullable();
$table->timestamp('end_date')->nullable();
$table->timestamps();
$table->softDeletes();
```

**Indexes**
```php
$table->index('status');
```

**Relations**
- `hasMany`: `Court`, `Category`, `Player`, `Match` via `tournament_id`

---

### 3. `2026_06_11_120002_create_courts_table`
- **Model:** `Court`
- **Table:** `courts`

**Fields**
```php
$table->uuid('id')->primary();
$table->uuid('tournament_id');
$table->string('name', 80);
$table->unsignedSmallInteger('display_order')->default(0);
$table->boolean('active')->default(true);
$table->timestamps();
$table->foreign('tournament_id')->references('id')->on('tournaments')->cascadeOnDelete();
```

**Indexes**
```php
$table->unique(['tournament_id', 'name']);
$table->index('tournament_id');
```

**Relations**
- `belongsTo`: `Tournament`
- `hasMany`: `Match`, `RefereeAssignment`

---

### 4. `2026_06_11_120003_create_categories_table`
- **Model:** `Category`
- **Table:** `categories`

**Fields**
```php
$table->uuid('id')->primary();
$table->uuid('tournament_id');
$table->enum('type', ['MENS_SINGLES', 'WOMENS_SINGLES', 'MENS_DOUBLES', 'WOMENS_DOUBLES', 'MIXED_DOUBLES']);
$table->string('name', 160);
$table->timestamps();
$table->foreign('tournament_id')->references('id')->on('tournaments')->cascadeOnDelete();
```

**Indexes**
```php
$table->unique(['tournament_id', 'type', 'name']);
$table->index('tournament_id');
```

**Relations**
- `belongsTo`: `Tournament`
- `hasMany`: `Team`, `Group`, `Match`

---

### 5. `2026_06_11_120004_create_players_table`
- **Model:** `Player`
- **Table:** `players`

**Fields**
```php
$table->uuid('id')->primary();
$table->uuid('tournament_id');
$table->string('full_name', 160);
$table->enum('gender', ['MALE', 'FEMALE']);
$table->string('club', 160)->nullable();
$table->string('contact', 160)->nullable();
$table->timestamps();
$table->foreign('tournament_id')->references('id')->on('tournaments')->cascadeOnDelete();
```

**Indexes**
```php
$table->index('tournament_id');
```

**Relations**
- `belongsTo`: `Tournament`
- `belongsToMany`: `Team` via `team_players`

---

### 6. `2026_06_11_120005_create_teams_table`
- **Model:** `Team`
- **Table:** `teams`

**Fields**
```php
$table->uuid('id')->primary();
$table->uuid('category_id');
$table->string('display_name', 200)->nullable();
$table->unsignedSmallInteger('seed')->nullable();
$table->timestamps();
$table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
```

**Indexes**
```php
$table->index('category_id');
```

**Relations**
- `belongsTo`: `Category`
- `belongsToMany`: `Player` via `team_players`
- `belongsToMany`: `Group` via `group_teams`
- `hasMany`: `Match` as `teamA` / `teamB` / `winner`; `hasMany` `MatchSet` as `winner`; `hasMany` `ScoreEvent`

---

### 7. `2026_06_11_120006_create_team_players_table`
- **Model:** `TeamPlayer`
- **Table:** `team_players`

**Fields**
```php
$table->uuid('team_id');
$table->uuid('player_id');
$table->unsignedSmallInteger('position')->default(1);
$table->primary(['team_id', 'player_id']);
$table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
$table->foreign('player_id')->references('id')->on('players')->cascadeOnDelete();
```

**Indexes**
```php
$table->unique(['team_id', 'position']);
$table->index('player_id');
```

**Relations**
- `belongsTo`: `Team`
- `belongsTo`: `Player`

---

### 8. `2026_06_11_120007_create_groups_table`
- **Model:** `Group`
- **Table:** `groups`

**Fields**
```php
$table->uuid('id')->primary();
$table->uuid('category_id');
$table->string('name', 80);
$table->timestamps();
$table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
```

**Indexes**
```php
$table->unique(['category_id', 'name']);
$table->index('category_id');
```

**Relations**
- `belongsTo`: `Category`
- `belongsToMany`: `Team` via `group_teams`
- `hasMany`: `Match`

---

### 9. `2026_06_11_120008_create_group_teams_table`
- **Model:** `GroupTeam`
- **Table:** `group_teams`

**Fields**
```php
$table->uuid('group_id');
$table->uuid('team_id');
$table->primary(['group_id', 'team_id']);
$table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
$table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
```

**Indexes**
```php
$table->index('team_id');
```

**Relations**
- `belongsTo`: `Group`
- `belongsTo`: `Team`

---

### 10. `2026_06_11_120009_create_matches_table`
- **Model:** `Match`
- **Table:** `matches`

**Fields**
```php
$table->uuid('id')->primary();
$table->uuid('tournament_id');
$table->uuid('category_id');
$table->uuid('court_id')->nullable();
$table->uuid('group_id')->nullable();
$table->enum('stage', ['GROUP', 'ROUND_OF_64', 'ROUND_OF_32', 'ROUND_OF_16', 'QUARTERFINAL', 'SEMIFINAL', 'FINAL', 'THIRD_PLACE']);
$table->unsignedSmallInteger('round_number')->nullable();
$table->unsignedInteger('bracket_slot')->nullable();
$table->uuid('next_match_id')->nullable();
$table->timestamp('scheduled_at')->nullable();
$table->timestamp('started_at')->nullable();
$table->timestamp('completed_at')->nullable();
$table->enum('status', ['SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'WALKOVER', 'CANCELLED'])->default('SCHEDULED');
$table->uuid('team_a_id')->nullable();
$table->uuid('team_b_id')->nullable();
$table->uuid('winner_id')->nullable();
$table->text('notes')->nullable();
$table->timestamps();
$table->foreign('tournament_id')->references('id')->on('tournaments')->cascadeOnDelete();
$table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
$table->foreign('court_id')->references('id')->on('courts')->nullOnDelete();
$table->foreign('group_id')->references('id')->on('groups')->nullOnDelete();
$table->foreign('team_a_id')->references('id')->on('teams')->nullOnDelete();
$table->foreign('team_b_id')->references('id')->on('teams')->nullOnDelete();
$table->foreign('winner_id')->references('id')->on('teams')->nullOnDelete();
// next_match_id FK added in follow-up migration to avoid self-reference ordering issues
```

**Indexes**
```php
$table->index(['tournament_id', 'status']);
$table->index('category_id');
$table->index(['court_id', 'status']);
$table->index('group_id');
$table->index('team_a_id');
$table->index('team_b_id');
$table->index('winner_id');
$table->index('next_match_id');
```

**Relations**
- `belongsTo`: `Tournament`, `Category`, `Court` (nullable), `Group` (nullable), `Team` as `teamA` / `teamB` / `winner` (nullable)
- self-ref: `nextMatch` (bracket progression) with `prevMatches` inverse

---

### 11. `2026_06_11_120010_add_next_match_fk_to_matches_table`
- **Model:** `Match` (self-FK)
- **Table:** `matches`

**Fields**
```php
// Schema::table('matches', ...) — add the self-referential FK after the table exists
$table->foreign('next_match_id')->references('id')->on('matches')->nullOnDelete();
```

**Indexes**
- (none — declared in the main matches migration)

**Relations**
- self-ref FK `matches.next_match_id -> matches.id` (`ON DELETE SET NULL`)

---

### 12. `2026_06_11_120011_create_match_sets_table`
- **Model:** `MatchSet`
- **Table:** `match_sets`

**Fields**
```php
$table->uuid('id')->primary();
$table->uuid('match_id');
$table->unsignedSmallInteger('set_number');
$table->unsignedSmallInteger('team_a_score')->default(0);
$table->unsignedSmallInteger('team_b_score')->default(0);
$table->uuid('winner_id')->nullable();
$table->timestamp('started_at')->nullable();
$table->timestamp('completed_at')->nullable();
$table->timestamps();
$table->foreign('match_id')->references('id')->on('matches')->cascadeOnDelete();
$table->foreign('winner_id')->references('id')->on('teams')->nullOnDelete();
```

**Indexes**
```php
$table->unique(['match_id', 'set_number']);
$table->index('match_id');
$table->index('winner_id');
```

**Relations**
- `belongsTo`: `Match`, `Team` `winner` (nullable)

---

### 13. `2026_06_11_120012_create_score_events_table`
- **Model:** `ScoreEvent`
- **Table:** `score_events`

**Fields**
```php
$table->uuid('id')->primary();
$table->uuid('match_id');
$table->unsignedSmallInteger('set_number');
$table->uuid('scoring_team_id');
$table->uuid('scored_by_user_id')->nullable();
$table->unsignedSmallInteger('team_a_score_after');
$table->unsignedSmallInteger('team_b_score_after');
$table->boolean('undone')->default(false);
$table->timestamp('scored_at')->useCurrent();
$table->timestamps();
$table->foreign('match_id')->references('id')->on('matches')->cascadeOnDelete();
$table->foreign('scoring_team_id')->references('id')->on('teams')->cascadeOnDelete();
$table->foreign('scored_by_user_id')->references('id')->on('users')->nullOnDelete();
```

**Indexes**
```php
$table->index(['match_id', 'scored_at']);
$table->index('scoring_team_id');
$table->index('scored_by_user_id');
$table->index(['match_id', 'undone']);
```

**Relations**
- `belongsTo`: `Match`, `Team` `scoringTeam`, `User` `scoredByUser` (nullable)

---

### 14. `2026_06_11_120013_create_referee_assignments_table`
- **Model:** `RefereeAssignment`
- **Table:** `referee_assignments`

**Fields**
```php
$table->uuid('id')->primary();
$table->uuid('user_id');
$table->uuid('court_id');
$table->boolean('active')->default(true);
$table->timestamps();
$table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
$table->foreign('court_id')->references('id')->on('courts')->cascadeOnDelete();
```

**Indexes**
```php
$table->unique(['user_id', 'court_id']);
$table->index(['court_id', 'active']);
$table->index('user_id');
```

**Relations**
- `belongsTo`: `User`, `Court`

---

## Notes

### Primary keys
Prisma uses `cuid()` strings. Laravel switches to UUID v4 via `$table->uuid('id')->primary()`. Models should use the `HasUuids` trait. All FK columns are also `uuid()`.

### Enum strategy
We use Laravel's `$table->enum()` helper. On Postgres this creates a `CHECK` constraint on a `varchar` — portable across MySQL/SQLite without committing to Postgres ENUM types. Values mirror Prisma exactly (UPPER_SNAKE) so existing `ScoringConfig` and DTOs still work after the port.

### Soft deletes
Only `tournaments` has `$table->softDeletes()`. Every child table (`courts`, `categories`, `players`, `matches`, etc.) hard-cascades via `cascadeOnDelete()` so deleting a tournament removes the entire hierarchy. `teams` and `groups` cascade through `categories`. The `team_players` and `group_teams` pivots cascade on either side.

### Null-on-delete
Matches keep history when a court / group / team is removed — `court_id`, `group_id`, `team_a_id`, `team_b_id`, `winner_id`, and `next_match_id` all use `nullOnDelete()`. The same applies to `match_sets.winner_id` and `score_events.scored_by_user_id`.

### Self-referencing FK
`matches.next_match_id` references `matches.id`. Laravel migrations cannot reliably add a self-FK inside the same `CREATE TABLE` block across all drivers, so the FK is added in a separate follow-up migration (`2026_06_11_120010`). The column itself is declared in the main matches migration with an index but no constraint.

### Composite indexes (hot paths)
- `(tournament_id, status)`
- `(court_id, status)`
- `(group_id)`
- `(category_id)`
- `(match_id, set_number)` UNIQUE
- `(match_id, scored_at)` for the score-event tail-scan undo lookup
- `(match_id, undone)` so live "current score" queries skip undone events quickly

Every FK column also gets its own single-column index. This is aggressive, but the user explicitly asked for sub-100ms reads.

### Pivot tables
- `team_players` uses composite PK `(team_id, player_id)` plus `UNIQUE (team_id, position)` so doubles can't have two players in slot 1.
- `group_teams` uses `(group_id, team_id)` PK with a secondary index on `team_id` for the reverse lookup.

### Timestamps
Every table gets `$table->timestamps()`. Prisma's `match_sets` and `score_events` only have `started_at` / `completed_at` / `scored_at` — adding `created_at`/`updated_at` is harmless and matches Laravel convention. `score_events.scored_at` is kept as a separate column (it's the authoritative event time and is what `scoring-rules.ts` replay reads, distinct from `created_at`).

### Unique constraints preserved
- `users.username`
- `courts(tournament_id, name)`
- `categories(tournament_id, type, name)`
- `groups(category_id, name)`
- `match_sets(match_id, set_number)`
- `referee_assignments(user_id, court_id)`
- `team_players(team_id, position)`

### Scoring-rules port
`scoring-rules.ts` is pure (no DB). Port to `app/Scoring/ScoringRules.php` as static helpers — `emptyMatchState()`, `setWinner(SetState, ScoringConfig)`, `scorePoint(MatchScoreState, 'A'|'B', ScoringConfig)`, `replayPoints(events[], config)`, `maxSets(config)`. The `ScoreEvent` table is the append-only log; "undo" flips the `undone` flag and the service replays the remaining ordered events through `scorePoint()` to derive current score — `match_sets.team_a_score` / `team_b_score` are denormalized snapshots updated on each event for fast public reads.

### Column sizes
Strings are sized for the badminton domain — `name(200)`, `full_name(160)`, `club(160)`, `venue(200)`, `username(80)`, court `name(80)`, group `name(80)`. Smallints for scores/counts (0–99 range).

### Migration order
Sequential `120000..120013`. Order respects FK dependencies:

`users` → `tournaments` → `courts` / `categories` / `players` → `teams` → `team_players` → `groups` → `group_teams` → `matches` → matches self-FK → `match_sets` → `score_events` → `referee_assignments`.

### Postgres note
Prisma uses `timestamp(3)` with millisecond precision; Laravel's default `timestamp` is second precision. If existing data is migrated, add `->precision(3)` to the timestamp columns — especially `scored_at`, since undo order matters for events landing in the same second.
