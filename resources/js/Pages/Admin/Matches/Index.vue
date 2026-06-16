<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

type TeamRef = {
  id: string | null;
  displayName: string;
  resolved?: boolean;
} | null;

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
  teamA: TeamRef;
  teamB: TeamRef;
  teamsResolved: boolean;
  winnerId: string | null;
  winnerName: string | null;
  sets: SetRow[];
  scoringConfig: ScoringConfig;
};

const props = defineProps<{
  matches: Match[];
}>();

// Filters are client-side: the index ships the full match list.
const categoryFilter = ref<string>('');
const statusFilter = ref<string>('');

const STATUS_LABELS: Record<string, string> = {
  SCHEDULED: 'Scheduled',
  IN_PROGRESS: 'In Progress',
  COMPLETED: 'Completed',
  WALKOVER: 'Walkover',
  CANCELLED: 'Cancelled',
};

const STAGE_LABELS: Record<string, string> = {
  GROUP: 'Group',
  ROUND_OF_64: 'Round of 64',
  ROUND_OF_32: 'Round of 32',
  ROUND_OF_16: 'Round of 16',
  QUARTERFINAL: 'Quarterfinal',
  SEMIFINAL: 'Semifinal',
  FINAL: 'Final',
  THIRD_PLACE: 'Third Place',
};

// Distinct category names present in the data, for the category <select>.
const categoryOptions = computed(() => {
  const seen = new Map<string, string>();
  for (const m of props.matches) {
    if (m.category_id && m.categoryName) seen.set(m.category_id, m.categoryName);
  }
  return [...seen.entries()].map(([id, name]) => ({ id, name }));
});

const filtered = computed(() =>
  props.matches.filter((m) => {
    if (categoryFilter.value && m.category_id !== categoryFilter.value) return false;
    if (statusFilter.value && m.status !== statusFilter.value) return false;
    return true;
  }),
);

function statusChip(status: string | null): string {
  switch (status) {
    case 'IN_PROGRESS':
      return 'chip chip-live';
    case 'SCHEDULED':
      return 'chip chip-soon';
    default:
      return 'chip chip-done';
  }
}

function statusLabel(status: string | null): string {
  return status ? (STATUS_LABELS[status] ?? status) : '—';
}

function stageLabel(stage: string | null): string {
  return stage ? (STAGE_LABELS[stage] ?? stage) : '—';
}

function teamName(team: TeamRef): string {
  return team?.displayName || 'TBD';
}

// "21-15" or "15-12, 13-15, 15-10" for completed matches; "" otherwise.
function scoreSummary(m: Match): string {
  if (!m.sets || m.sets.length === 0) return '';
  return m.sets
    .slice()
    .sort((x, y) => x.setNumber - y.setNumber)
    .map((s) => `${s.teamAScore}-${s.teamBScore}`)
    .join(', ');
}

function isCompleted(m: Match): boolean {
  return m.status === 'COMPLETED' || m.status === 'WALKOVER';
}

// "2026-06-13T18:30" -> "13 Jun 2026, 18:30" (already Asia/Yangon from server).
function formatSchedule(value: string | null): string {
  if (!value) return 'Unscheduled';
  const [date, time] = value.split('T');
  const [y, mo, d] = date.split('-');
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const month = months[Number(mo) - 1] ?? mo;
  return `${Number(d)} ${month} ${y}, ${time}`;
}

function resetFilters() {
  categoryFilter.value = '';
  statusFilter.value = '';
}

// ===== Inline result editor =====
// Only one row open at a time. Opening a row (re)builds the editor state.
const openMatchId = ref<string | null>(null);

type EditableSet = { a: string; b: string };
const editor = reactive<{ rows: EditableSet[]; walkoverWinner: string }>({
  rows: [],
  walkoverWinner: '',
});

// A single useForm reused per open row (sets payload built on submit).
const resultForm = useForm<{ sets: { teamAScore: number; teamBScore: number }[] }>({ sets: [] });
const walkoverForm = useForm<{ winner_team_id: string }>({ winner_team_id: '' });
const resetForm = useForm({});

// up to setsToWin*2 - 1 rows (group: 1, best-of-3: 3).
function maxSets(m: Match): number {
  return m.scoringConfig.setsToWin * 2 - 1;
}

function buildRows(m: Match): EditableSet[] {
  const ordered = (m.sets ?? []).slice().sort((x, y) => x.setNumber - y.setNumber);
  const rows: EditableSet[] = [];
  for (let i = 0; i < maxSets(m); i++) {
    const existing = ordered[i];
    rows.push({
      a: existing ? String(existing.teamAScore) : '',
      b: existing ? String(existing.teamBScore) : '',
    });
  }
  return rows;
}

function openEditor(m: Match) {
  if (openMatchId.value === m.id) {
    openMatchId.value = null;
    return;
  }
  openMatchId.value = m.id;
  editor.rows = buildRows(m);
  editor.walkoverWinner = m.winnerId ?? '';
  resultForm.clearErrors();
  walkoverForm.clearErrors();
}

function closeEditor() {
  openMatchId.value = null;
}

function isOpen(m: Match): boolean {
  return openMatchId.value === m.id;
}

// Per-set winner: who first reaches pointsToWin with a 2-point lead, capped at deuceCap.
function setWinnerId(m: Match, a: number, b: number): string | null {
  const { pointsToWin, deuceCap } = m.scoringConfig;
  const hi = Math.max(a, b);
  const lo = Math.min(a, b);
  if (hi < pointsToWin) return null;
  if (hi >= deuceCap) {
    if (hi - lo < 1) return null;
  } else if (hi - lo < 2) {
    return null;
  }
  if (a === b) return null;
  return a > b ? m.teamA?.id ?? null : m.teamB?.id ?? null;
}

// Only consider rows that were actually filled in (both fields numeric).
function filledSets(): { a: number; b: number }[] {
  return editor.rows
    .map((r) => ({ a: Number(r.a), b: Number(r.b), raw: r }))
    .filter((r) => r.raw.a !== '' && r.raw.b !== '' && !Number.isNaN(r.a) && !Number.isNaN(r.b))
    .map((r) => ({ a: r.a, b: r.b }));
}

function preview(m: Match): { aSets: number; bSets: number; winnerId: string | null; invalid: boolean } {
  let aSets = 0;
  let bSets = 0;
  let invalid = false;
  for (const s of filledSets()) {
    const w = setWinnerId(m, s.a, s.b);
    if (w === null) {
      invalid = true;
      continue;
    }
    if (w === m.teamA?.id) aSets++;
    else if (w === m.teamB?.id) bSets++;
  }
  const need = m.scoringConfig.setsToWin;
  let winnerId: string | null = null;
  if (aSets >= need && aSets > bSets) winnerId = m.teamA?.id ?? null;
  else if (bSets >= need && bSets > aSets) winnerId = m.teamB?.id ?? null;
  return { aSets, bSets, winnerId, invalid };
}

function previewWinnerName(m: Match): string | null {
  const w = preview(m).winnerId;
  if (!w) return null;
  return w === m.teamA?.id ? teamName(m.teamA) : teamName(m.teamB);
}

const livePreview = computed(() => {
  const m = props.matches.find((x) => x.id === openMatchId.value);
  if (!m) return null;
  const p = preview(m);
  return { ...p, winnerName: previewWinnerName(m) };
});

function saveResult(m: Match) {
  resultForm
    .transform(() => ({
      sets: filledSets().map((s) => ({ teamAScore: s.a, teamBScore: s.b })),
    }))
    .put(route('admin.matches.result', { match: m.id }), {
      preserveScroll: true,
      onSuccess: () => closeEditor(),
    });
}

function declareWalkover(m: Match) {
  if (!editor.walkoverWinner) return;
  if (!confirm('Declare a walkover? This overwrites any existing result.')) return;
  walkoverForm.winner_team_id = editor.walkoverWinner;
  walkoverForm.put(route('admin.matches.walkover', { match: m.id }), {
    preserveScroll: true,
    onSuccess: () => closeEditor(),
  });
}

function resetResult(m: Match) {
  if (!confirm('Reset this match back to Scheduled? Set scores and bracket progression will be cleared.')) {
    return;
  }
  resetForm.put(route('admin.matches.reset', { match: m.id }), {
    preserveScroll: true,
    onSuccess: () => closeEditor(),
  });
}

function resultLabel(m: Match): string {
  return isCompleted(m) ? 'Edit result' : 'Enter result';
}
</script>

<template>
  <Head title="Matches" />
  <AdminLayout>
    <div class="flex items-center justify-between mb-5">
      <h1 class="text-2xl font-bold text-slate-900">Matches</h1>
      <span class="text-sm text-slate-500">{{ filtered.length }} of {{ props.matches.length }}</span>
    </div>

    <!-- Filters -->
    <div class="card mb-5">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div>
          <label class="label" for="filter-category">Category</label>
          <select id="filter-category" v-model="categoryFilter" class="input">
            <option value="">All categories</option>
            <option v-for="c in categoryOptions" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div>
          <label class="label" for="filter-status">Status</label>
          <select id="filter-status" v-model="statusFilter" class="input">
            <option value="">All statuses</option>
            <option v-for="(label, value) in STATUS_LABELS" :key="value" :value="value">{{ label }}</option>
          </select>
        </div>
        <div class="flex items-end">
          <button
            class="btn-secondary text-sm"
            :disabled="!categoryFilter && !statusFilter"
            @click="resetFilters"
          >
            Reset filters
          </button>
        </div>
      </div>
    </div>

    <div v-if="props.matches.length === 0" class="card text-center">
      <div class="text-4xl mb-2">🏸</div>
      <p class="text-slate-500">No matches yet. Matches are generated by the draw engine.</p>
    </div>

    <div v-else-if="filtered.length === 0" class="card text-center">
      <p class="text-slate-500">No matches match the current filters.</p>
      <button class="btn-secondary text-sm mt-3" @click="resetFilters">Reset filters</button>
    </div>

    <!-- Mobile: stacked cards -->
    <div v-else class="space-y-3 lg:hidden">
      <div v-for="m in filtered" :key="m.id" class="card">
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="text-xs uppercase tracking-wider text-slate-400">{{ m.categoryName || '—' }}</div>
            <div class="mt-0.5 font-semibold text-slate-900">
              {{ teamName(m.teamA) }} <span class="text-slate-400 font-normal">vs</span> {{ teamName(m.teamB) }}
            </div>
          </div>
          <span :class="statusChip(m.status)">{{ statusLabel(m.status) }}</span>
        </div>
        <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
          <div><dt class="text-slate-400">Stage</dt><dd class="text-slate-700">{{ stageLabel(m.stage) }}</dd></div>
          <div><dt class="text-slate-400">Round</dt><dd class="text-slate-700">{{ m.round_number ?? '—' }}</dd></div>
          <div><dt class="text-slate-400">Court</dt><dd class="text-slate-700">{{ m.courtName || '—' }}</dd></div>
          <div><dt class="text-slate-400">Scheduled</dt><dd class="text-slate-700">{{ formatSchedule(m.scheduledAt) }}</dd></div>
          <div v-if="isCompleted(m)" class="col-span-2">
            <dt class="text-slate-400">Score</dt>
            <dd class="text-slate-700">
              <span class="font-mono">{{ scoreSummary(m) || '—' }}</span>
              <span v-if="m.winnerName" class="text-emerald-700 font-medium"> · {{ m.winnerName }}</span>
            </dd>
          </div>
        </dl>
        <div class="mt-3 flex justify-end gap-2">
          <button type="button" class="btn-primary text-xs px-3 py-1.5" @click="openEditor(m)">
            {{ isOpen(m) ? 'Close' : resultLabel(m) }}
          </button>
          <Link :href="route('admin.matches.edit', { match: m.id })" class="btn-secondary text-xs px-3 py-1.5">Edit</Link>
        </div>

        <!-- Inline result editor (mobile) -->
        <div v-if="isOpen(m)" class="mt-4 border-t border-slate-100 pt-4">
          <!-- Teams not determined -->
          <div
            v-if="!m.teamsResolved"
            class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
          >
            Teams not determined yet — resolve the bracket first.
          </div>
          <template v-else>
            <p class="text-xs text-slate-500 mb-3">
              Best of {{ maxSets(m) }} ·
              to {{ m.scoringConfig.pointsToWin }} pts ·
              win {{ m.scoringConfig.setsToWin }} set{{ m.scoringConfig.setsToWin > 1 ? 's' : '' }} ·
              cap {{ m.scoringConfig.deuceCap }}. Fill only sets that were played.
            </p>

            <div class="space-y-3">
              <div
                v-for="(row, i) in editor.rows"
                :key="i"
                class="grid grid-cols-[3rem_1fr_1fr] gap-3 items-end"
              >
                <div class="text-sm font-medium text-slate-600 pb-2">Set {{ i + 1 }}</div>
                <div>
                  <span class="block text-xs text-slate-400 mb-1 truncate">{{ teamName(m.teamA) }}</span>
                  <input
                    v-model="row.a"
                    type="number"
                    min="0"
                    inputmode="numeric"
                    class="input"
                    :aria-label="`Set ${i + 1} ${teamName(m.teamA)} score`"
                  />
                </div>
                <div>
                  <span class="block text-xs text-slate-400 mb-1 truncate">{{ teamName(m.teamB) }}</span>
                  <input
                    v-model="row.b"
                    type="number"
                    min="0"
                    inputmode="numeric"
                    class="input"
                    :aria-label="`Set ${i + 1} ${teamName(m.teamB)} score`"
                  />
                </div>
              </div>
            </div>

            <!-- Live winner preview -->
            <div v-if="livePreview" class="mt-3 rounded-lg bg-white px-4 py-3 text-sm border border-slate-100">
              <div class="flex items-center justify-between">
                <span class="text-slate-500">Sets won</span>
                <span class="font-mono text-slate-700">{{ livePreview.aSets }} – {{ livePreview.bSets }}</span>
              </div>
              <div class="mt-1 flex items-center justify-between">
                <span class="text-slate-500">Projected winner</span>
                <span v-if="livePreview.winnerName" class="font-semibold text-emerald-700">{{ livePreview.winnerName }}</span>
                <span v-else-if="livePreview.invalid" class="text-amber-600">Incomplete / invalid set</span>
                <span v-else class="text-slate-400">Not decided yet</span>
              </div>
            </div>

            <div v-if="resultForm.errors.sets" class="mt-2 text-sm text-red-600">{{ resultForm.errors.sets }}</div>

            <div class="mt-3 flex flex-col gap-3">
              <div class="flex flex-wrap items-center gap-2">
                <button
                  type="button"
                  class="btn-primary text-sm"
                  :disabled="resultForm.processing || filledSets().length === 0"
                  @click="saveResult(m)"
                >
                  Save result
                </button>
                <button
                  v-if="isCompleted(m)"
                  type="button"
                  class="btn-secondary text-sm"
                  :disabled="resetForm.processing"
                  @click="resetResult(m)"
                >
                  Reset
                </button>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- Desktop: table -->
    <div v-if="filtered.length > 0" class="card overflow-x-auto p-0 hidden lg:block">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead>
          <tr class="text-left text-xs uppercase tracking-wider text-slate-400">
            <th class="px-4 py-3 font-semibold">Category</th>
            <th class="px-4 py-3 font-semibold">Stage</th>
            <th class="px-4 py-3 font-semibold">Round</th>
            <th class="px-4 py-3 font-semibold">Match</th>
            <th class="px-4 py-3 font-semibold">Score</th>
            <th class="px-4 py-3 font-semibold">Court</th>
            <th class="px-4 py-3 font-semibold">Scheduled</th>
            <th class="px-4 py-3 font-semibold">Status</th>
            <th class="px-4 py-3 font-semibold text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <template v-for="m in filtered" :key="m.id">
            <tr class="hover:bg-slate-50" :class="{ 'bg-slate-50': isOpen(m) }">
              <td class="px-4 py-3 text-slate-500">{{ m.categoryName || '—' }}</td>
              <td class="px-4 py-3 text-slate-500">{{ stageLabel(m.stage) }}</td>
              <td class="px-4 py-3 text-slate-500 font-mono">{{ m.round_number ?? '—' }}</td>
              <td class="px-4 py-3 font-medium text-slate-900">
                {{ teamName(m.teamA) }} <span class="text-slate-400 font-normal">vs</span> {{ teamName(m.teamB) }}
              </td>
              <td class="px-4 py-3">
                <template v-if="isCompleted(m)">
                  <div class="font-mono text-slate-700">{{ scoreSummary(m) || '—' }}</div>
                  <div v-if="m.winnerName" class="text-xs text-emerald-700 font-medium">{{ m.winnerName }}</div>
                </template>
                <span v-else class="text-slate-300">—</span>
              </td>
              <td class="px-4 py-3 text-slate-500">{{ m.courtName || '—' }}</td>
              <td class="px-4 py-3 text-slate-500">{{ formatSchedule(m.scheduledAt) }}</td>
              <td class="px-4 py-3">
                <span :class="statusChip(m.status)">{{ statusLabel(m.status) }}</span>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-end gap-2">
                  <button type="button" class="btn-primary text-xs px-3 py-1.5" @click="openEditor(m)">
                    {{ isOpen(m) ? 'Close' : resultLabel(m) }}
                  </button>
                  <Link
                    :href="route('admin.matches.edit', { match: m.id })"
                    class="btn-secondary text-xs px-3 py-1.5"
                  >
                    Edit
                  </Link>
                </div>
              </td>
            </tr>
            <!-- Inline result editor (desktop, expanding detail row) -->
            <tr v-if="isOpen(m)" class="bg-slate-50">
              <td colspan="9" class="px-4 py-4">
                <!-- Teams not determined -->
                <div
                  v-if="!m.teamsResolved"
                  class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 max-w-2xl"
                >
                  Teams not determined yet — resolve the bracket first.
                </div>
                <div v-else class="max-w-2xl">
                  <p class="text-xs text-slate-500 mb-3">
                    Best of {{ maxSets(m) }} ·
                    to {{ m.scoringConfig.pointsToWin }} pts ·
                    win {{ m.scoringConfig.setsToWin }} set{{ m.scoringConfig.setsToWin > 1 ? 's' : '' }} ·
                    cap {{ m.scoringConfig.deuceCap }}. Fill only sets that were played.
                  </p>

                  <div class="grid grid-cols-[3rem_1fr_1fr] gap-3 text-xs uppercase tracking-wider text-slate-400 mb-1">
                    <div></div>
                    <div class="truncate">{{ teamName(m.teamA) }}</div>
                    <div class="truncate">{{ teamName(m.teamB) }}</div>
                  </div>

                  <div class="space-y-2">
                    <div
                      v-for="(row, i) in editor.rows"
                      :key="i"
                      class="grid grid-cols-[3rem_1fr_1fr] gap-3 items-center"
                    >
                      <div class="text-sm font-medium text-slate-600">Set {{ i + 1 }}</div>
                      <input
                        v-model="row.a"
                        type="number"
                        min="0"
                        inputmode="numeric"
                        class="input"
                        :aria-label="`Set ${i + 1} ${teamName(m.teamA)} score`"
                      />
                      <input
                        v-model="row.b"
                        type="number"
                        min="0"
                        inputmode="numeric"
                        class="input"
                        :aria-label="`Set ${i + 1} ${teamName(m.teamB)} score`"
                      />
                    </div>
                  </div>

                  <!-- Live winner preview -->
                  <div v-if="livePreview" class="mt-3 rounded-lg bg-white px-4 py-3 text-sm border border-slate-100">
                    <div class="flex items-center justify-between">
                      <span class="text-slate-500">Sets won</span>
                      <span class="font-mono text-slate-700">{{ livePreview.aSets }} – {{ livePreview.bSets }}</span>
                    </div>
                    <div class="mt-1 flex items-center justify-between">
                      <span class="text-slate-500">Projected winner</span>
                      <span v-if="livePreview.winnerName" class="font-semibold text-emerald-700">{{ livePreview.winnerName }}</span>
                      <span v-else-if="livePreview.invalid" class="text-amber-600">Incomplete / invalid set</span>
                      <span v-else class="text-slate-400">Not decided yet</span>
                    </div>
                  </div>

                  <div v-if="resultForm.errors.sets" class="mt-2 text-sm text-red-600">{{ resultForm.errors.sets }}</div>
      
                  <div class="mt-3 flex flex-wrap items-center gap-3">
                    <button
                      type="button"
                      class="btn-primary text-sm"
                      :disabled="resultForm.processing || filledSets().length === 0"
                      @click="saveResult(m)"
                    >
                      Save result
                    </button>
                    <button
                      v-if="isCompleted(m)"
                      type="button"
                      class="btn-secondary text-sm"
                      :disabled="resetForm.processing"
                      @click="resetResult(m)"
                    >
                      Reset
                    </button>
                  </div>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
