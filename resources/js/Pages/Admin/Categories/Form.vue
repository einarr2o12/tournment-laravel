<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

type Category = {
  id: string;
  tournament_id: string;
  tournamentName?: string | null;
  type?: string | null;
  name: string;
};

type TournamentOption = {
  id: string;
  name: string;
};

const props = defineProps<{
  // null = create mode; present = edit mode.
  category: Category | null;
  tournaments: TournamentOption[];
  // value => label map from CategoryType::labels().
  types: Record<string, string>;
}>();

const isEdit = computed(() => props.category !== null);

// useForm field keys are snake_case — they match the CategoryRequest rules()
// and the controller's $request->validated() directly (no transform needed).
const form = useForm({
  tournament_id: props.category?.tournament_id ?? props.tournaments[0]?.id ?? '',
  type: props.category?.type ?? Object.keys(props.types)[0] ?? '',
  name: props.category?.name ?? '',
});

function submit() {
  if (isEdit.value && props.category) {
    form.put(route('admin.categories.update', { category: props.category.id }));
  } else {
    form.post(route('admin.categories.store'));
  }
}
</script>

<template>
  <Head :title="isEdit ? 'Edit category' : 'New category'" />
  <AdminLayout>
    <div class="flex items-center gap-3 mb-5">
      <Link :href="route('admin.categories.index')" class="text-sm font-medium text-slate-500 hover:text-brand-700">
        ← Categories
      </Link>
    </div>

    <h1 class="text-2xl font-bold text-slate-900 mb-5">
      {{ isEdit ? 'Edit category' : 'New category' }}
    </h1>

    <form class="card max-w-xl space-y-5" @submit.prevent="submit">
      <div>
        <label class="label" for="name">Name</label>
        <input id="name" v-model="form.name" type="text" class="input" placeholder="Men's Singles A" />
        <p v-if="form.errors.name" class="text-sm text-red-600 mt-1">{{ form.errors.name }}</p>
      </div>

      <div>
        <label class="label" for="type">Type</label>
        <select id="type" v-model="form.type" class="input">
          <option v-for="(label, value) in props.types" :key="value" :value="value">{{ label }}</option>
        </select>
        <p v-if="form.errors.type" class="text-sm text-red-600 mt-1">{{ form.errors.type }}</p>
      </div>

      <div>
        <label class="label" for="tournament_id">Tournament</label>
        <select id="tournament_id" v-model="form.tournament_id" class="input">
          <option v-for="t in props.tournaments" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>
        <p v-if="form.errors.tournament_id" class="text-sm text-red-600 mt-1">{{ form.errors.tournament_id }}</p>
      </div>

      <div class="flex items-center gap-3 pt-2">
        <button type="submit" class="btn-primary text-sm" :disabled="form.processing">
          {{ isEdit ? 'Save changes' : 'Create category' }}
        </button>
        <Link :href="route('admin.categories.index')" class="btn-secondary text-sm">Cancel</Link>
      </div>
    </form>
  </AdminLayout>
</template>
