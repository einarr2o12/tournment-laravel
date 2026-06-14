<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

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
  courtsCount: number;
  categoriesCount: number;
  playersCount: number;
  matchesCount: number;
};

const props = defineProps<{
  tournaments: Tournament[];
}>();

const statusLabel: Record<string, string> = {
  DRAFT: 'Draft',
  SCHEDULED: 'Scheduled',
  IN_PROGRESS: 'In Progress',
  COMPLETED: 'Completed',
  ARCHIVED: 'Archived',
};

function chipClass(status?: string | null): string {
  if (status === 'IN_PROGRESS') return 'chip chip-live';
  if (status === 'SCHEDULED') return 'chip chip-soon';
  return 'chip chip-done';
}
</script>

<template>
  <Head title="Tournaments" />
  <AdminLayout>
    <h1 class="text-2xl font-bold text-slate-900 mb-5">Tournaments</h1>

    <div v-if="props.tournaments.length === 0" class="card text-center">
      <div class="text-4xl mb-2">🏆</div>
      <p class="text-slate-500">No tournaments yet.</p>
    </div>

    <div v-else class="space-y-4">
      <div v-for="t in props.tournaments" :key="t.id" class="card">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="flex items-center gap-2">
              <h2 class="text-lg font-semibold text-slate-900">{{ t.name }}</h2>
              <span :class="chipClass(t.status)">{{ statusLabel[t.status ?? ''] ?? t.status }}</span>
            </div>
            <p class="text-sm text-slate-500 mt-1">{{ t.venue || 'No venue' }}</p>
            <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-400 mt-2">
              <span>{{ t.courtsCount }} courts</span>
              <span>{{ t.categoriesCount }} categories</span>
              <span>{{ t.playersCount }} players</span>
              <span>{{ t.matchesCount }} matches</span>
            </div>
          </div>
          <Link
            :href="route('admin.tournaments.edit', { tournament: t.id })"
            class="btn-secondary text-sm"
          >
            Edit settings
          </Link>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
