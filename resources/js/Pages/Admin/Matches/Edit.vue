<script setup lang="ts">
import { computed, reactive } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

type TeamRef = {
  id: string | null;
  displayName: string;
  resolved: boolean;
};

type Match = {
  id: string;
  tournament_id: string;
  category_id: string | null;
  categoryName?: string | null;
  court_id: string | null;
  courtName?: string | null;
  stage: string | null;
  status: string | null;
  round_number: number | null;
  bracket_slot: number | null;
  scheduledAt: string | null;
  startedAt: string | null;
  completedAt: string | null;
  teamA: TeamRef;
  teamB: TeamRef;
  teamsResolved: boolean;
  winnerId: string | null;
  notes: string | null;
};

type SetRow = {
  setNumber: number;
  teamAScore: number;
  teamBScore: number;
  winnerId: string | null;
};

type ScoringConfig = {
  pointsToWin: number;
  setsToWin: number;
  deuceCap: number;
};

type CourtOption = {
  id: string;
  name: string;
};

const props = defineProps<{
  match: Match;
  sets: SetRow[];
  scoringConfig: ScoringConfig;
  courts: CourtOption[];
  statuses: Record<string, string>;
  stages: Record<string, string>;
}>();

// ----- Schedule form (existing behaviour, unchanged) -----
const form = useForm({
  court_id: props.match.court_id ?? '',
  scheduled_at: props.match.scheduledAt ?? '',
  status: props.match.status ?? 'SCHEDULED',
  notes: props.match.notes ?? '',
});

const teamAName = computed(() => props.match.teamA?.displayName || 'TBD');
const teamBName = computed(() => props.match.teamB?.displayName || 'TBD');
const stageLabel = computed(() =>
  props.match.stage ? (props.stages[props.match.stage] ?? props.match.stage) : '—',
);

function submit() {
  form
    .transform((data) => ({
      ...data,
      court_id: data.court_id || null,
      scheduled_at: data.scheduled_at || null,
      notes: data.notes || null,
    }))
    .put(route('admin.matches.update', { match: props.match.id }));
}

// ----- Result section -----
const teamsResolved = computed(() => props.match.teamsResolved);
const teamAId = computed(() => props.match.teamA?.id ?? null);
const teamBId = computed(() => props.match.teamB?.id ?? null);

// up to setsToWin*2 - 1 rows (group: 1, best-of-3: 3).
const maxSets = computed(() => props.scoringConfig.setsToWin * 2 - 1);

// Editable set inputs, pre-filled from any existing result.
type EditableSet = { a: string; b: string };
function buildSetState(): EditableSet[] {
  const rows: EditableSet[] = [];
  for (let i = 0; i < maxSets.value; i++) {
    const existing = props.sets[i];
    rows.push({
      a: existing ? String(existing.teamAScore) : '',
      b: existing ? String(existing.teamBScore) : '',
    });
  }
  return rows;
}
const setState = reactive<{ rows: EditableSet[] }>({ rows: buildSetState() });

// Per-set winner: who first reaches pointsToWin with a 2-point lead, capped at deuceCap.
function setWinnerId(a: number, b: number): string | null {
  const { pointsToWin, deuceCap } = props.scoringConfig;
  const hi = Math.max(a, b);
  const lo = Math.min(a, b);
  if (hi < pointsToWin) return null;
  // Win at cap with a single-point lead, else need a 2-point margin.
  if (hi >= deuceCap) {
    if (hi - lo < 1) return null;
  } else if (hi - lo < 2) {
    return null;
  }
  if (a === b) return null;
  return a > b ? teamAId.value : teamBId.value;
}

// Only consider rows that were actually filled in (both fields non-empty).
const filledSets = computed(() =>
  setState.rows
    .map((r, i) => ({ i, a: Number(r.a), b: Number(r.b), raw: r }))
    .filter((r) => r.raw.a !== '' && r.raw.b !== '' && !Number.isNaN(r.a) && !Number.isNaN(r.b)),
);

const preview = computed(() => {
  let aSets = 0;
  let bSets = 0;
  let invalid = false;
  for (const s of filledSets.value) {
    const w = setWinnerId(s.a, s.b);
    if (w === null) {
      invalid = true;
      continue;
    }
    if (w === teamAId.value) aSets++;
    else if (w === teamBId.value) bSets++;
  }
  const need = props.scoringConfig.setsToWin;
  let winnerId: string | null = null;
  if (aSets >= need && aSets > bSets) winnerId = teamAId.value;
  else if (bSets >= need && bSets > aSets) winnerId = teamBId.value;
  return { aSets, bSets, winnerId, invalid };
});

const previewWinnerName = computed(() => {
  const w = preview.value.winnerId;
  if (!w) return null;
  return w === teamAId.value ? teamAName.value : teamBName.value;
});

// ----- Save result -----
const resultForm = useForm<{ sets: { teamAScore: number; teamBScore: number }[] }>({
  sets: [],
});

function saveResult() {
  resultForm
    .transform(() => ({
      sets: filledSets.value.map((s) => ({ teamAScore: s.a, teamBScore: s.b })),
    }))
    .put(route('admin.matches.result', { match: props.match.id }), {
      preserveScroll: true,
    });
}

// ----- Walkover -----
const walkoverForm = useForm<{ winner_team_id: string }>({
  winner_team_id: props.match.winnerId ?? '',
});

function declareWalkover() {
  if (!walkoverForm.winner_team_id) return;
  if (!confirm('Declare a walkover? This overwrites any existing result.')) return;
  walkoverForm.put(route('admin.matches.walkover', { match: props.match.id }), {
    preserveScroll: true,
  });
}

// ----- Reset -----
const resetForm = useForm({});

function resetResult() {
  if (!confirm('Reset this match back to Scheduled? Set scores and bracket progression will be cleared.')) {
    return;
  }
  resetForm.put(route('admin.matches.reset', { match: props.match.id }), {
    preserveScroll: true,
  });
}

const isCompleted = computed(
  () => props.match.status === 'COMPLETED' || props.match.status === 'WALKOVER',
);
</script>

<template>
  <Head title="Edit match" />
  <AdminLayout>
    <div class="flex items-center gap-3 mb-5">
      <Link :href="route('admin.matches.index')" class="text-sm font-medium text-slate-500 hover:text-brand-700">
        ← Matches
      </Link>
    </div>

    <h1 class="text-2xl font-bold text-slate-900 mb-1">Edit match</h1>
    <p class="text-slate-500 mb-5">Assign court, schedule, and status — or record the result directly.</p>

    <!-- Read-only match context -->
    <div class="card max-w-xl mb-5">
      <div class="text-xs uppercase tracking-wider text-slate-400">{{ props.match.categoryName || '—' }}</div>
      <div class="mt-1 text-lg font-semibold text-slate-900">
        {{ teamAName }} <span class="text-slate-400 font-normal">vs</span> {{ teamBName }}
      </div>
      <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
        <div><dt class="text-slate-400">Stage</dt><dd class="text-slate-700">{{ stageLabel }}</dd></div>
        <div><dt class="text-slate-400">Round</dt><dd class="text-slate-700">{{ props.match.round_number ?? '—' }}</dd></div>
      </dl>
    </div>

    <!-- Schedule form -->
    <form class="card max-w-xl space-y-5 mb-5" @submit.prevent="submit">
      <h2 class="text-base font-semibold text-slate-900">Schedule</h2>

      <div>
        <label class="label" for="court_id">Court</label>
        <select id="court_id" v-model="form.court_id" class="input">
          <option value="">— Unassigned —</option>
          <option v-for="c in props.courts" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <p v-if="form.errors.court_id" class="text-sm text-red-600 mt-1">{{ form.errors.court_id }}</p>
      </div>

      <div>
        <label class="label" for="scheduled_at">Scheduled at <span class="font-normal text-slate-400">(Asia/Yangon)</span></label>
        <input id="scheduled_at" v-model="form.scheduled_at" type="datetime-local" class="input" />
        <p v-if="form.errors.scheduled_at" class="text-sm text-red-600 mt-1">{{ form.errors.scheduled_at }}</p>
      </div>

      <div>
        <label class="label" for="status">Status</label>
        <select id="status" v-model="form.status" class="input">
          <option v-for="(label, value) in props.statuses" :key="value" :value="value">{{ label }}</option>
        </select>
        <p v-if="form.errors.status" class="text-sm text-red-600 mt-1">{{ form.errors.status }}</p>
      </div>

      <div>
        <label class="label" for="notes">Notes</label>
        <textarea id="notes" v-model="form.notes" rows="3" class="input" placeholder="Optional notes (max 1000 chars)"></textarea>
        <p v-if="form.errors.notes" class="text-sm text-red-600 mt-1">{{ form.errors.notes }}</p>
      </div>

      <div class="flex items-center gap-3 pt-2">
        <button type="submit" class="btn-primary text-sm" :disabled="form.processing">Save changes</button>
        <Link :href="route('admin.matches.index')" class="btn-secondary text-sm">Cancel</Link>
      </div>
    </form>

    <!-- Result section -->
    <div class="card max-w-xl space-y-5">
      <div class="flex items-center justify-between">
        <h2 class="text-base font-semibold text-slate-900">Result</h2>
        <span v-if="isCompleted" class="chip chip-done">{{ props.statuses[props.match.status ?? ''] ?? 'Completed' }}</span>
      </div>

      <!-- Teams not determined -->
      <div
        v-if="!teamsResolved"
        class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
      >
        Teams not determined yet — resolve the bracket first.
      </div>

      <template v-else>
        <p class="text-xs text-slate-500">
          Best of {{ maxSets }} ·
          to {{ props.scoringConfig.pointsToWin }} pts ·
          win {{ props.scoringConfig.setsToWin }} set{{ props.scoringConfig.setsToWin > 1 ? 's' : '' }} ·
          cap {{ props.scoringConfig.deuceCap }}.
          Fill only the sets that were played.
        </p>

        <!-- Column headers (wide) -->
        <div class="hidden sm:grid grid-cols-[3rem_1fr_1fr] gap-3 text-xs uppercase tracking-wider text-slate-400">
          <div></div>
          <div class="truncate">{{ teamAName }}</div>
          <div class="truncate">{{ teamBName }}</div>
        </div>

        <!-- Set rows -->
        <div class="space-y-3">
          <div
            v-for="(row, i) in setState.rows"
            :key="i"
            class="grid grid-cols-[3rem_1fr_1fr] gap-3 items-center"
          >
            <div class="text-sm font-medium text-slate-600">Set {{ i + 1 }}</div>
            <div>
              <span class="sm:hidden block text-xs text-slate-400 mb-1 truncate">{{ teamAName }}</span>
              <input
                v-model="row.a"
                type="number"
                min="0"
                inputmode="numeric"
                class="input"
                :aria-label="`Set ${i + 1} ${teamAName} score`"
              />
            </div>
            <div>
              <span class="sm:hidden block text-xs text-slate-400 mb-1 truncate">{{ teamBName }}</span>
              <input
                v-model="row.b"
                type="number"
                min="0"
                inputmode="numeric"
                class="input"
                :aria-label="`Set ${i + 1} ${teamBName} score`"
              />
            </div>
          </div>
        </div>

        <!-- Live winner preview -->
        <div class="rounded-lg bg-slate-50 px-4 py-3 text-sm">
          <div class="flex items-center justify-between">
            <span class="text-slate-500">Sets won</span>
            <span class="font-mono text-slate-700">{{ preview.aSets }} – {{ preview.bSets }}</span>
          </div>
          <div class="mt-1 flex items-center justify-between">
            <span class="text-slate-500">Projected winner</span>
            <span v-if="previewWinnerName" class="font-semibold text-emerald-700">{{ previewWinnerName }}</span>
            <span v-else-if="preview.invalid" class="text-amber-600">Incomplete / invalid set</span>
            <span v-else class="text-slate-400">Not decided yet</span>
          </div>
          <p class="mt-1 text-xs text-slate-400">Preview only — the server validates and records the result.</p>
        </div>

        <!-- Backend validation errors -->
        <div v-if="resultForm.errors.sets" class="text-sm text-red-600">{{ resultForm.errors.sets }}</div>
        <div v-if="walkoverForm.errors.winner_team_id" class="text-sm text-red-600">{{ walkoverForm.errors.winner_team_id }}</div>

        <!-- Actions -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 pt-1">
          <button
            type="button"
            class="btn-primary text-sm"
            :disabled="resultForm.processing || filledSets.length === 0"
            @click="saveResult"
          >
            Save result
          </button>
          <button
            v-if="isCompleted"
            type="button"
            class="btn-secondary text-sm"
            :disabled="resetForm.processing"
            @click="resetResult"
          >
            Reset to scheduled
          </button>
        </div>

        <!-- Walkover -->
        <div class="border-t border-slate-100 pt-4">
          <label class="label" for="walkover_winner">Walkover</label>
          <div class="flex flex-col sm:flex-row sm:items-center gap-3">
            <select id="walkover_winner" v-model="walkoverForm.winner_team_id" class="input sm:flex-1">
              <option value="">— Winner by walkover —</option>
              <option :value="teamAId">{{ teamAName }}</option>
              <option :value="teamBId">{{ teamBName }}</option>
            </select>
            <button
              type="button"
              class="btn-secondary text-sm whitespace-nowrap"
              :disabled="walkoverForm.processing || !walkoverForm.winner_team_id"
              @click="declareWalkover"
            >
              Declare walkover
            </button>
          </div>
        </div>
      </template>
    </div>
  </AdminLayout>
</template>
