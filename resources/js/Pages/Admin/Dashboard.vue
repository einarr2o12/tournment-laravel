<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '../../Layouts/AdminLayout.vue';

type Tournament = {
  id: string;
  name: string;
  venue?: string | null;
  format?: string | null;
  status?: string | null;
  points_to_win: number;
  sets_to_win: number;
  deuce_cap: number;
  startDate?: string | null;
  endDate?: string | null;
};

type Counts = {
  tournaments: number;
  courts: number;
  categories: number;
  teams: number;
  players: number;
  matches: {
    total: number;
    scheduled: number;
    inProgress: number;
    completed: number;
  };
};

const props = defineProps<{
  tournament: Tournament | null;
  counts: Counts;
}>();

const statusLabel: Record<string, string> = {
  DRAFT: 'Draft',
  SCHEDULED: 'Scheduled',
  IN_PROGRESS: 'In Progress',
  COMPLETED: 'Completed',
  ARCHIVED: 'Archived',
};

const formatLabel: Record<string, string> = {
  SINGLE_ELIMINATION: 'Single Elimination',
  ROUND_ROBIN: 'Round Robin',
  GROUP_KNOCKOUT: 'Group + Knockout',
  SWISS: 'Swiss',
};

const resourceCards = computed(() => [
  { label: 'Courts', value: props.counts.courts, routeName: 'admin.courts.index', icon: '🏟️' },
  { label: 'Categories', value: props.counts.categories, routeName: 'admin.categories.index', icon: '🗂️' },
  { label: 'Teams', value: props.counts.teams, routeName: 'admin.teams.index', icon: '👥' },
  { label: 'Players', value: props.counts.players, routeName: 'admin.players.index', icon: '🧑' },
]);

function fmtDate(iso?: string | null): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString();
}
</script>

<template>
  <Head title="Admin" />
  <AdminLayout>
    <div class="flex items-center justify-between mb-5">
      <h1 class="text-2xl font-bold text-slate-900">Dashboard</h1>
    </div>

    <!-- Active tournament -->
    <section v-if="props.tournament" class="card mb-6">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
          <div class="text-xs uppercase tracking-wider text-slate-400">Active tournament</div>
          <h2 class="text-xl font-semibold text-slate-900 mt-0.5">{{ props.tournament.name }}</h2>
          <p class="text-sm text-slate-500 mt-1">
            {{ props.tournament.venue || 'No venue' }}
            · {{ formatLabel[props.tournament.format ?? ''] ?? props.tournament.format }}
          </p>
        </div>
        <div class="flex items-center gap-2">
          <span class="chip chip-soon">{{ statusLabel[props.tournament.status ?? ''] ?? props.tournament.status }}</span>
          <Link
            :href="route('admin.tournaments.edit', { tournament: props.tournament.id })"
            class="btn-secondary text-sm"
          >
            Edit settings
          </Link>
        </div>
      </div>
      <dl class="grid grid-cols-2 sm:grid-cols-5 gap-4 mt-5 text-sm">
        <div>
          <dt class="text-slate-400">Points / set</dt>
          <dd class="font-semibold text-slate-900">{{ props.tournament.points_to_win }}</dd>
        </div>
        <div>
          <dt class="text-slate-400">Sets to win</dt>
          <dd class="font-semibold text-slate-900">{{ props.tournament.sets_to_win }}</dd>
        </div>
        <div>
          <dt class="text-slate-400">Deuce cap</dt>
          <dd class="font-semibold text-slate-900">{{ props.tournament.deuce_cap }}</dd>
        </div>
        <div>
          <dt class="text-slate-400">Start</dt>
          <dd class="font-semibold text-slate-900">{{ fmtDate(props.tournament.startDate) }}</dd>
        </div>
        <div>
          <dt class="text-slate-400">End</dt>
          <dd class="font-semibold text-slate-900">{{ fmtDate(props.tournament.endDate) }}</dd>
        </div>
      </dl>
    </section>
    <section v-else class="card mb-6 text-center">
      <div class="text-4xl mb-2">🏸</div>
      <p class="text-slate-500">No tournament yet.</p>
      <Link :href="route('admin.tournaments.index')" class="btn-primary text-sm mt-3">Manage tournaments</Link>
    </section>

    <!-- Resource counts -->
    <section class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <Link
        v-for="c in resourceCards"
        :key="c.label"
        :href="route(c.routeName)"
        class="card hover:ring-brand-400 hover:ring-2 transition"
      >
        <div class="flex items-center justify-between">
          <span class="text-2xl">{{ c.icon }}</span>
          <span class="text-3xl font-bold text-slate-900">{{ c.value }}</span>
        </div>
        <div class="mt-2 text-sm font-medium text-slate-500">{{ c.label }}</div>
      </Link>
    </section>

    <!-- Match breakdown -->
    <section class="card">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-slate-900">Matches</h2>
        <Link :href="route('admin.matches.index')" class="text-sm font-medium text-brand-600 hover:text-brand-700">
          View all →
        </Link>
      </div>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
        <div class="rounded-lg bg-slate-50 py-3">
          <div class="text-2xl font-bold text-slate-900">{{ props.counts.matches.total }}</div>
          <div class="text-xs text-slate-500 mt-0.5">Total</div>
        </div>
        <div class="rounded-lg bg-brand-50 py-3">
          <div class="text-2xl font-bold text-brand-700">{{ props.counts.matches.scheduled }}</div>
          <div class="text-xs text-brand-600 mt-0.5">Scheduled</div>
        </div>
        <div class="rounded-lg bg-live-500/10 py-3">
          <div class="text-2xl font-bold text-live-600">{{ props.counts.matches.inProgress }}</div>
          <div class="text-xs text-live-600 mt-0.5">In progress</div>
        </div>
        <div class="rounded-lg bg-slate-100 py-3">
          <div class="text-2xl font-bold text-slate-700">{{ props.counts.matches.completed }}</div>
          <div class="text-xs text-slate-500 mt-0.5">Completed</div>
        </div>
      </div>
    </section>
  </AdminLayout>
</template>
