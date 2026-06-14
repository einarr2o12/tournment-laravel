<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

type Court = {
  id: string;
  tournament_id: string;
  name: string;
  display_order: number;
  active: boolean;
};

type TournamentOption = {
  id: string;
  name: string;
};

const props = defineProps<{
  // null = create mode; present = edit mode.
  court: Court | null;
  tournaments: TournamentOption[];
}>();

const isEdit = computed(() => props.court !== null);

// useForm field keys are snake_case — they match the FormRequest rules() and
// the controller's $request->validated() directly (no transform needed).
const form = useForm({
  tournament_id: props.court?.tournament_id ?? props.tournaments[0]?.id ?? '',
  name: props.court?.name ?? '',
  display_order: props.court?.display_order ?? 0,
  active: props.court?.active ?? true,
});

function submit() {
  if (isEdit.value && props.court) {
    form.put(route('admin.courts.update', { court: props.court.id }));
  } else {
    form.post(route('admin.courts.store'));
  }
}
</script>

<template>
  <Head :title="isEdit ? 'Edit court' : 'New court'" />
  <AdminLayout>
    <div class="flex items-center gap-3 mb-5">
      <Link :href="route('admin.courts.index')" class="text-sm font-medium text-slate-500 hover:text-brand-700">
        ← Courts
      </Link>
    </div>

    <h1 class="text-2xl font-bold text-slate-900 mb-5">
      {{ isEdit ? 'Edit court' : 'New court' }}
    </h1>

    <form class="card max-w-xl space-y-5" @submit.prevent="submit">
      <div>
        <label class="label" for="tournament_id">Tournament</label>
        <select id="tournament_id" v-model="form.tournament_id" class="input">
          <option v-for="t in props.tournaments" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>
        <p v-if="form.errors.tournament_id" class="text-sm text-red-600 mt-1">{{ form.errors.tournament_id }}</p>
      </div>

      <div>
        <label class="label" for="name">Name</label>
        <input id="name" v-model="form.name" type="text" class="input" placeholder="Court 1" />
        <p v-if="form.errors.name" class="text-sm text-red-600 mt-1">{{ form.errors.name }}</p>
      </div>

      <div>
        <label class="label" for="display_order">Display order</label>
        <input id="display_order" v-model.number="form.display_order" type="number" min="0" class="input" />
        <p v-if="form.errors.display_order" class="text-sm text-red-600 mt-1">{{ form.errors.display_order }}</p>
      </div>

      <div class="flex items-center gap-2">
        <input id="active" v-model="form.active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-200" />
        <label for="active" class="text-sm font-medium text-slate-700">Active</label>
      </div>
      <p v-if="form.errors.active" class="text-sm text-red-600">{{ form.errors.active }}</p>

      <div class="flex items-center gap-3 pt-2">
        <button type="submit" class="btn-primary text-sm" :disabled="form.processing">
          {{ isEdit ? 'Save changes' : 'Create court' }}
        </button>
        <Link :href="route('admin.courts.index')" class="btn-secondary text-sm">Cancel</Link>
      </div>
    </form>
  </AdminLayout>
</template>
