<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

type TeamRef = {
  id: string | null;
  displayName: string;
} | null;

type SetRow = {
  setNumber: number;
  teamAScore: number;
  teamBScore: number;
  winnerId: string | null;
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
  winnerId: string | null;
  winnerName: string | null;
  sets: SetRow[];
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
        <div class="mt-3 flex justify-end">
          <Link :href="route('admin.matches.edit', { match: m.id })" class="btn-secondary text-xs px-3 py-1.5">Edit</Link>
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
          <tr v-for="m in filtered" :key="m.id" class="hover:bg-slate-50">
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
                <Link
                  :href="route('admin.matches.edit', { match: m.id })"
                  class="btn-secondary text-xs px-3 py-1.5"
                >
                  Edit
                </Link>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
