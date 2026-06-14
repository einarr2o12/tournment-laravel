<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

type TeamPlayer = {
  id: string;
  full_name: string;
  position: number;
};

type Team = {
  id: string;
  category_id: string;
  categoryName?: string | null;
  tournamentName?: string | null;
  display_name: string;
  seed: number | null;
  players: TeamPlayer[];
  playerIds: string[];
};

const props = defineProps<{
  teams: Team[];
}>();

// Group teams by category so the table reads as one block per category.
const groups = computed(() => {
  const map = new Map<string, { key: string; categoryName: string; tournamentName: string; teams: Team[] }>();
  for (const team of props.teams) {
    const key = team.category_id ?? '—';
    if (!map.has(key)) {
      map.set(key, {
        key,
        categoryName: team.categoryName ?? 'Uncategorized',
        tournamentName: team.tournamentName ?? '',
        teams: [],
      });
    }
    map.get(key)!.teams.push(team);
  }
  return Array.from(map.values());
});

function memberNames(team: Team): string {
  if (!team.players.length) return '—';
  return team.players.map((p) => p.full_name).join(' & ');
}

function destroy(team: Team) {
  if (!window.confirm(`Delete team "${team.display_name}"?`)) return;
  router.delete(route('admin.teams.destroy', { team: team.id }));
}
</script>

<template>
  <Head title="Teams" />
  <AdminLayout>
    <div class="flex items-center justify-between mb-5">
      <h1 class="text-2xl font-bold text-slate-900">Teams</h1>
      <Link :href="route('admin.teams.create')" class="btn-primary text-sm">+ New team</Link>
    </div>

    <div v-if="props.teams.length === 0" class="card text-center">
      <div class="text-4xl mb-2">👥</div>
      <p class="text-slate-500">No teams yet.</p>
      <Link :href="route('admin.teams.create')" class="btn-primary text-sm mt-3">+ New team</Link>
    </div>

    <div v-else class="space-y-6">
      <section v-for="group in groups" :key="group.key" class="card p-0 overflow-hidden">
        <header class="flex flex-wrap items-baseline gap-x-2 gap-y-1 px-4 py-3 border-b border-slate-100 bg-slate-50/60">
          <h2 class="text-sm font-bold text-slate-900">{{ group.categoryName }}</h2>
          <span v-if="group.tournamentName" class="text-xs text-slate-400">· {{ group.tournamentName }}</span>
          <span class="chip chip-soon ml-auto">{{ group.teams.length }} team{{ group.teams.length === 1 ? '' : 's' }}</span>
        </header>

        <!-- Mobile: stacked cards -->
        <ul class="divide-y divide-slate-100 sm:hidden">
          <li v-for="team in group.teams" :key="team.id" class="px-4 py-3">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="font-medium text-slate-900 truncate">{{ team.display_name }}</p>
                <p class="text-sm text-slate-500 truncate">{{ memberNames(team) }}</p>
                <p v-if="team.seed != null" class="text-xs text-slate-400 mt-0.5">Seed #{{ team.seed }}</p>
              </div>
              <div class="flex shrink-0 flex-col gap-1.5">
                <Link
                  :href="route('admin.teams.edit', { team: team.id })"
                  class="btn-secondary text-xs px-3 py-1.5"
                >
                  Edit
                </Link>
                <button
                  class="btn text-xs px-3 py-1.5 bg-red-50 text-red-600 ring-1 ring-red-200 hover:bg-red-100"
                  @click="destroy(team)"
                >
                  Delete
                </button>
              </div>
            </div>
          </li>
        </ul>

        <!-- Desktop: table -->
        <div class="hidden sm:block overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead>
              <tr class="text-left text-xs uppercase tracking-wider text-slate-400">
                <th class="px-4 py-3 font-semibold">Team</th>
                <th class="px-4 py-3 font-semibold">Seed</th>
                <th class="px-4 py-3 font-semibold">Members</th>
                <th class="px-4 py-3 font-semibold text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="team in group.teams" :key="team.id" class="hover:bg-slate-50">
                <td class="px-4 py-3 font-medium text-slate-900">{{ team.display_name }}</td>
                <td class="px-4 py-3 text-slate-500 font-mono">{{ team.seed != null ? `#${team.seed}` : '—' }}</td>
                <td class="px-4 py-3 text-slate-500">
                  <span class="inline-flex flex-wrap gap-1.5">
                    <span
                      v-for="player in team.players"
                      :key="player.id"
                      class="chip chip-done"
                    >
                      {{ player.position }}. {{ player.full_name }}
                    </span>
                    <span v-if="!team.players.length">—</span>
                  </span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center justify-end gap-2">
                    <Link
                      :href="route('admin.teams.edit', { team: team.id })"
                      class="btn-secondary text-xs px-3 py-1.5"
                    >
                      Edit
                    </Link>
                    <button
                      class="btn text-xs px-3 py-1.5 bg-red-50 text-red-600 ring-1 ring-red-200 hover:bg-red-100"
                      @click="destroy(team)"
                    >
                      Delete
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>
