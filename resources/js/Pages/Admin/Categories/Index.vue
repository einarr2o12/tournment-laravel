<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

type Category = {
  id: string;
  tournament_id: string;
  tournamentName?: string | null;
  type?: string | null;
  name: string;
  teamsCount: number;
  groupsCount: number;
  matchesCount: number;
};

const props = defineProps<{
  categories: Category[];
}>();

// value => label map mirrors CategoryType::labels() so the table shows the
// friendly label rather than the raw enum value.
const TYPE_LABELS: Record<string, string> = {
  MENS_SINGLES: "Men's Singles",
  WOMENS_SINGLES: "Women's Singles",
  MENS_DOUBLES: "Men's Doubles",
  WOMENS_DOUBLES: "Women's Doubles",
  MIXED_DOUBLES: 'Mixed Doubles',
};

function typeLabel(type?: string | null): string {
  if (!type) return '—';
  return TYPE_LABELS[type] ?? type;
}

function destroy(category: Category) {
  if (!window.confirm(`Delete category "${category.name}"?`)) return;
  router.delete(route('admin.categories.destroy', { category: category.id }));
}
</script>

<template>
  <Head title="Categories" />
  <AdminLayout>
    <div class="flex items-center justify-between mb-5">
      <h1 class="text-2xl font-bold text-slate-900">Categories</h1>
      <Link :href="route('admin.categories.create')" class="btn-primary text-sm">+ New category</Link>
    </div>

    <div v-if="props.categories.length === 0" class="card text-center">
      <div class="text-4xl mb-2">🏷️</div>
      <p class="text-slate-500">No categories yet.</p>
      <Link :href="route('admin.categories.create')" class="btn-primary text-sm mt-3">+ New category</Link>
    </div>

    <div v-else class="card overflow-x-auto p-0">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead>
          <tr class="text-left text-xs uppercase tracking-wider text-slate-400">
            <th class="px-4 py-3 font-semibold">Name</th>
            <th class="px-4 py-3 font-semibold">Type</th>
            <th class="px-4 py-3 font-semibold">Tournament</th>
            <th class="px-4 py-3 font-semibold">Teams</th>
            <th class="px-4 py-3 font-semibold text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="category in props.categories" :key="category.id" class="hover:bg-slate-50">
            <td class="px-4 py-3 font-medium text-slate-900">{{ category.name }}</td>
            <td class="px-4 py-3">
              <span class="chip-soon">{{ typeLabel(category.type) }}</span>
            </td>
            <td class="px-4 py-3 text-slate-500">{{ category.tournamentName || '—' }}</td>
            <td class="px-4 py-3 text-slate-500 font-mono">{{ category.teamsCount }}</td>
            <td class="px-4 py-3">
              <div class="flex items-center justify-end gap-2">
                <Link
                  :href="route('admin.categories.edit', { category: category.id })"
                  class="btn-secondary text-xs px-3 py-1.5"
                >
                  Edit
                </Link>
                <button
                  class="btn text-xs px-3 py-1.5 bg-red-50 text-red-600 ring-1 ring-red-200 hover:bg-red-100"
                  @click="destroy(category)"
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
