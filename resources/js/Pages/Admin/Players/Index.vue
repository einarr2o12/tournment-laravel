<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

type Player = {
  id: string;
  tournament_id: string;
  tournamentName?: string | null;
  full_name: string;
  gender: string | null;
  club: string | null;
  contact: string | null;
};

const props = defineProps<{
  players: Player[];
}>();

const genderLabels: Record<string, string> = {
  MALE: 'Male',
  FEMALE: 'Female',
};

const search = ref('');

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return props.players;
  return props.players.filter((p) => {
    const genderLabel = p.gender ? (genderLabels[p.gender] ?? p.gender) : '';
    return (
      p.full_name.toLowerCase().includes(q) ||
      genderLabel.toLowerCase().includes(q) ||
      (p.club ?? '').toLowerCase().includes(q)
    );
  });
});

function destroy(player: Player) {
  if (!window.confirm(`Delete player "${player.full_name}"?`)) return;
  router.delete(route('admin.players.destroy', { player: player.id }));
}
</script>

<template>
  <Head title="Players" />
  <AdminLayout>
    <div class="flex items-center justify-between gap-3 mb-5">
      <h1 class="text-2xl font-bold text-slate-900">Players</h1>
      <Link :href="route('admin.players.create')" class="btn-primary text-sm whitespace-nowrap">+ New player</Link>
    </div>

    <div v-if="props.players.length === 0" class="card text-center">
      <div class="text-4xl mb-2">🏸</div>
      <p class="text-slate-500">No players yet.</p>
      <Link :href="route('admin.players.create')" class="btn-primary text-sm mt-3">+ New player</Link>
    </div>

    <template v-else>
      <div class="mb-4">
        <input
          v-model="search"
          type="search"
          class="input max-w-sm"
          placeholder="Search name, gender, club…"
          aria-label="Search players"
        />
      </div>

      <div v-if="filtered.length === 0" class="card text-center text-slate-500">
        No players match “{{ search }}”.
      </div>

      <!-- Mobile: stacked cards -->
      <div v-else class="grid gap-3 sm:hidden">
        <div v-for="player in filtered" :key="player.id" class="card">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="font-semibold text-slate-900">{{ player.full_name }}</p>
              <p class="text-sm text-slate-500">{{ player.club || '—' }}</p>
            </div>
            <span class="chip chip-soon">{{ player.gender ? (genderLabels[player.gender] ?? player.gender) : '—' }}</span>
          </div>
          <p class="text-xs text-slate-400 mt-2">{{ player.tournamentName || 'No tournament' }}</p>
          <div class="flex items-center gap-2 mt-3">
            <Link
              :href="route('admin.players.edit', { player: player.id })"
              class="btn-secondary text-xs px-3 py-1.5 flex-1 text-center"
            >
              Edit
            </Link>
            <button
              class="btn text-xs px-3 py-1.5 bg-red-50 text-red-600 ring-1 ring-red-200 hover:bg-red-100"
              @click="destroy(player)"
            >
              Delete
            </button>
          </div>
        </div>
      </div>

      <!-- Desktop: table -->
      <div v-if="filtered.length > 0" class="card overflow-x-auto p-0 hidden sm:block">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
          <thead>
            <tr class="text-left text-xs uppercase tracking-wider text-slate-400">
              <th class="px-4 py-3 font-semibold">Full name</th>
              <th class="px-4 py-3 font-semibold">Gender</th>
              <th class="px-4 py-3 font-semibold">Club</th>
              <th class="px-4 py-3 font-semibold">Tournament</th>
              <th class="px-4 py-3 font-semibold text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="player in filtered" :key="player.id" class="hover:bg-slate-50">
              <td class="px-4 py-3 font-medium text-slate-900">{{ player.full_name }}</td>
              <td class="px-4 py-3">
                <span class="chip chip-soon">{{ player.gender ? (genderLabels[player.gender] ?? player.gender) : '—' }}</span>
              </td>
              <td class="px-4 py-3 text-slate-500">{{ player.club || '—' }}</td>
              <td class="px-4 py-3 text-slate-500">{{ player.tournamentName || '—' }}</td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-end gap-2">
                  <Link
                    :href="route('admin.players.edit', { player: player.id })"
                    class="btn-secondary text-xs px-3 py-1.5"
                  >
                    Edit
                  </Link>
                  <button
                    class="btn text-xs px-3 py-1.5 bg-red-50 text-red-600 ring-1 ring-red-200 hover:bg-red-100"
                    @click="destroy(player)"
                  >
                    Delete
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </AdminLayout>
</template>
