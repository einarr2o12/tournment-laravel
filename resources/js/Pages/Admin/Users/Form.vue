<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
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
  // null = create mode; present = edit mode.
  user: User | null;
  roles: Record<string, string>;
}>();

const isEdit = computed(() => props.user !== null);

// useForm field keys are snake_case — they match the FormRequest rules() and
// the controller's $request->validated() directly. `password` is plaintext;
// the controller hashes it into password_hash (required on create, optional on
// edit — leave blank to keep the existing one).
const form = useForm({
  username: props.user?.username ?? '',
  full_name: props.user?.full_name ?? '',
  role: props.user?.role ?? Object.keys(props.roles)[0] ?? '',
  active: props.user?.active ?? true,
  password: '',
});

function submit() {
  if (isEdit.value && props.user) {
    form.put(route('admin.users.update', { user: props.user.id }));
  } else {
    form.post(route('admin.users.store'));
  }
}
</script>

<template>
  <Head :title="isEdit ? 'Edit user' : 'New user'" />
  <AdminLayout>
    <div class="flex items-center gap-3 mb-5">
      <Link :href="route('admin.users.index')" class="text-sm font-medium text-slate-500 hover:text-brand-700">
        ← Users
      </Link>
    </div>

    <h1 class="text-2xl font-bold text-slate-900 mb-5">
      {{ isEdit ? 'Edit user' : 'New user' }}
    </h1>

    <form class="card max-w-xl space-y-5" @submit.prevent="submit">
      <div>
        <label class="label" for="username">Username</label>
        <input id="username" v-model="form.username" type="text" class="input" autocomplete="off" placeholder="referee1" />
        <p v-if="form.errors.username" class="text-sm text-red-600 mt-1">{{ form.errors.username }}</p>
      </div>

      <div>
        <label class="label" for="full_name">Full name</label>
        <input id="full_name" v-model="form.full_name" type="text" class="input" placeholder="Jane Doe" />
        <p v-if="form.errors.full_name" class="text-sm text-red-600 mt-1">{{ form.errors.full_name }}</p>
      </div>

      <div>
        <label class="label" for="role">Role</label>
        <select id="role" v-model="form.role" class="input">
          <option v-for="(label, value) in props.roles" :key="value" :value="value">{{ label }}</option>
        </select>
        <p v-if="form.errors.role" class="text-sm text-red-600 mt-1">{{ form.errors.role }}</p>
      </div>

      <div>
        <label class="label" for="password">
          Password
          <span v-if="isEdit" class="font-normal text-slate-400">(leave blank to keep current)</span>
        </label>
        <input
          id="password"
          v-model="form.password"
          type="password"
          class="input"
          autocomplete="new-password"
          :placeholder="isEdit ? '••••••••' : 'At least 8 characters'"
        />
        <p v-if="form.errors.password" class="text-sm text-red-600 mt-1">{{ form.errors.password }}</p>
      </div>

      <div class="flex items-center gap-2">
        <input id="active" v-model="form.active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-200" />
        <label for="active" class="text-sm font-medium text-slate-700">Active</label>
      </div>
      <p v-if="form.errors.active" class="text-sm text-red-600">{{ form.errors.active }}</p>

      <div class="flex items-center gap-3 pt-2">
        <button type="submit" class="btn-primary text-sm" :disabled="form.processing">
          {{ isEdit ? 'Save changes' : 'Create user' }}
        </button>
        <Link :href="route('admin.users.index')" class="btn-secondary text-sm">Cancel</Link>
      </div>
    </form>
  </AdminLayout>
</template>
