<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

type User = {
  id: string;
  username: string;
  full_name: string | null;
  role: string | null;
  active: boolean;
  lastLoginAt?: string | null;
};

const props = defineProps<{
  users: User[];
}>();

const roleLabels: Record<string, string> = {
  ADMIN: 'Administrator',
  REFEREE: 'Referee',
};

function roleLabel(role: string | null): string {
  return role ? (roleLabels[role] ?? role) : '—';
}

function formatLogin(value?: string | null): string {
  if (!value) return 'Never';
  return new Date(value).toLocaleString();
}

function destroy(user: User) {
  if (!window.confirm(`Delete user "${user.username}"?`)) return;
  router.delete(route('admin.users.destroy', { user: user.id }));
}
</script>

<template>
  <Head title="Users" />
  <AdminLayout>
    <div class="flex items-center justify-between mb-5">
      <h1 class="text-2xl font-bold text-slate-900">Users</h1>
      <Link :href="route('admin.users.create')" class="btn-primary text-sm">+ New user</Link>
    </div>

    <div v-if="props.users.length === 0" class="card text-center">
      <div class="text-4xl mb-2">👤</div>
      <p class="text-slate-500">No users yet.</p>
      <Link :href="route('admin.users.create')" class="btn-primary text-sm mt-3">+ New user</Link>
    </div>

    <div v-else class="card overflow-x-auto p-0">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead>
          <tr class="text-left text-xs uppercase tracking-wider text-slate-400">
            <th class="px-4 py-3 font-semibold">Username</th>
            <th class="px-4 py-3 font-semibold">Full name</th>
            <th class="px-4 py-3 font-semibold">Role</th>
            <th class="px-4 py-3 font-semibold">Status</th>
            <th class="px-4 py-3 font-semibold">Last login</th>
            <th class="px-4 py-3 font-semibold text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="user in props.users" :key="user.id" class="hover:bg-slate-50">
            <td class="px-4 py-3 font-medium text-slate-900">{{ user.username }}</td>
            <td class="px-4 py-3 text-slate-500">{{ user.full_name || '—' }}</td>
            <td class="px-4 py-3">
              <span :class="user.role === 'ADMIN' ? 'chip chip-live' : 'chip chip-soon'">
                {{ roleLabel(user.role) }}
              </span>
            </td>
            <td class="px-4 py-3">
              <span :class="user.active ? 'chip chip-live' : 'chip chip-done'">
                {{ user.active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td class="px-4 py-3 text-slate-500">{{ formatLogin(user.lastLoginAt) }}</td>
            <td class="px-4 py-3">
              <div class="flex items-center justify-end gap-2">
                <Link
                  :href="route('admin.users.edit', { user: user.id })"
                  class="btn-secondary text-xs px-3 py-1.5"
                >
                  Edit
                </Link>
                <button
                  class="btn text-xs px-3 py-1.5 bg-red-50 text-red-600 ring-1 ring-red-200 hover:bg-red-100"
                  @click="destroy(user)"
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
