<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

type Court = {
  id: string;
  tournament_id: string;
  tournamentName?: string | null;
  name: string;
  display_order: number;
  active: boolean;
};

const props = defineProps<{
  courts: Court[];
}>();

function destroy(court: Court) {
  if (!window.confirm(`Delete court "${court.name}"?`)) return;
  router.delete(route('admin.courts.destroy', { court: court.id }));
}
</script>

<template>
  <Head title="Courts" />
  <AdminLayout>
    <div class="flex items-center justify-between mb-5">
      <h1 class="text-2xl font-bold text-slate-900">Courts</h1>
      <Link :href="route('admin.courts.create')" class="btn-primary text-sm">+ New court</Link>
    </div>

    <div v-if="props.courts.length === 0" class="card text-center">
      <div class="text-4xl mb-2">🏟️</div>
      <p class="text-slate-500">No courts yet.</p>
      <Link :href="route('admin.courts.create')" class="btn-primary text-sm mt-3">+ New court</Link>
    </div>

    <div v-else class="card overflow-x-auto p-0">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead>
          <tr class="text-left text-xs uppercase tracking-wider text-slate-400">
            <th class="px-4 py-3 font-semibold">Name</th>
            <th class="px-4 py-3 font-semibold">Tournament</th>
            <th class="px-4 py-3 font-semibold">Order</th>
            <th class="px-4 py-3 font-semibold">Status</th>
            <th class="px-4 py-3 font-semibold text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="court in props.courts" :key="court.id" class="hover:bg-slate-50">
            <td class="px-4 py-3 font-medium text-slate-900">{{ court.name }}</td>
            <td class="px-4 py-3 text-slate-500">{{ court.tournamentName || '—' }}</td>
            <td class="px-4 py-3 text-slate-500 font-mono">{{ court.display_order }}</td>
            <td class="px-4 py-3">
              <span :class="court.active ? 'chip chip-live' : 'chip chip-done'">
                {{ court.active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center justify-end gap-2">
                <Link
                  :href="route('admin.courts.edit', { court: court.id })"
                  class="btn-secondary text-xs px-3 py-1.5"
                >
                  Edit
                </Link>
                <button
                  class="btn text-xs px-3 py-1.5 bg-red-50 text-red-600 ring-1 ring-red-200 hover:bg-red-100"
                  @click="destroy(court)"
                >
                  Delete
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
