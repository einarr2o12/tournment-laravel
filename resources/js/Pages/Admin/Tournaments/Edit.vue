<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

type Tournament = {
  id: string;
  name: string;
  description?: string | null;
  venue?: string | null;
  format?: string | null;
  status?: string | null;
  points_to_win: number;
  sets_to_win: number;
  deuce_cap: number;
  group_points_to_win?: number | null;
  group_sets_to_win?: number | null;
  group_deuce_cap?: number | null;
  startDate?: string | null; // YYYY-MM-DD
  endDate?: string | null; // YYYY-MM-DD
};

const props = defineProps<{
  tournament: Tournament;
  // value => label maps from the enum ::labels() helpers.
  statuses: Record<string, string>;
  formats: Record<string, string>;
}>();

const form = useForm({
  name: props.tournament.name ?? '',
  description: props.tournament.description ?? '',
  venue: props.tournament.venue ?? '',
  format: props.tournament.format ?? '',
  status: props.tournament.status ?? '',
  points_to_win: props.tournament.points_to_win ?? 21,
  sets_to_win: props.tournament.sets_to_win ?? 2,
  deuce_cap: props.tournament.deuce_cap ?? 30,
  group_points_to_win: props.tournament.group_points_to_win ?? null,
  group_sets_to_win: props.tournament.group_sets_to_win ?? null,
  group_deuce_cap: props.tournament.group_deuce_cap ?? null,
  start_date: props.tournament.startDate ?? '',
  end_date: props.tournament.endDate ?? '',
});

function submit() {
  form.put(route('admin.tournaments.update', { tournament: props.tournament.id }));
}
</script>

<template>
  <Head :title="`Edit ${props.tournament.name}`" />
  <AdminLayout>
    <div class="flex items-center gap-3 mb-5">
      <Link :href="route('admin.tournaments.index')" class="text-sm font-medium text-slate-500 hover:text-brand-700">
        ← Tournaments
      </Link>
    </div>

    <h1 class="text-2xl font-bold text-slate-900 mb-5">Edit tournament settings</h1>

    <form class="card max-w-2xl space-y-5" @submit.prevent="submit">
      <div>
        <label class="label" for="name">Name</label>
        <input id="name" v-model="form.name" type="text" class="input" />
        <p v-if="form.errors.name" class="text-sm text-red-600 mt-1">{{ form.errors.name }}</p>
      </div>

      <div>
        <label class="label" for="venue">Venue</label>
        <input id="venue" v-model="form.venue" type="text" class="input" />
        <p v-if="form.errors.venue" class="text-sm text-red-600 mt-1">{{ form.errors.venue }}</p>
      </div>

      <div>
        <label class="label" for="description">Description</label>
        <textarea id="description" v-model="form.description" rows="3" class="input"></textarea>
        <p v-if="form.errors.description" class="text-sm text-red-600 mt-1">{{ form.errors.description }}</p>
      </div>

      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="label" for="status">Status</label>
          <select id="status" v-model="form.status" class="input">
            <option v-for="(label, value) in props.statuses" :key="value" :value="value">{{ label }}</option>
          </select>
          <p v-if="form.errors.status" class="text-sm text-red-600 mt-1">{{ form.errors.status }}</p>
        </div>
        <div>
          <label class="label" for="format">Format</label>
          <select id="format" v-model="form.format" class="input">
            <option v-for="(label, value) in props.formats" :key="value" :value="value">{{ label }}</option>
          </select>
          <p v-if="form.errors.format" class="text-sm text-red-600 mt-1">{{ form.errors.format }}</p>
        </div>
      </div>

      <div>
        <h2 class="text-sm font-semibold text-slate-700 mb-2">Knockout scoring</h2>
        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="label" for="points_to_win">Points / set</label>
            <input id="points_to_win" v-model.number="form.points_to_win" type="number" min="1" class="input" />
            <p v-if="form.errors.points_to_win" class="text-sm text-red-600 mt-1">{{ form.errors.points_to_win }}</p>
          </div>
          <div>
            <label class="label" for="sets_to_win">Sets to win</label>
            <input id="sets_to_win" v-model.number="form.sets_to_win" type="number" min="1" class="input" />
            <p v-if="form.errors.sets_to_win" class="text-sm text-red-600 mt-1">{{ form.errors.sets_to_win }}</p>
          </div>
          <div>
            <label class="label" for="deuce_cap">Deuce cap</label>
            <input id="deuce_cap" v-model.number="form.deuce_cap" type="number" min="1" class="input" />
            <p v-if="form.errors.deuce_cap" class="text-sm text-red-600 mt-1">{{ form.errors.deuce_cap }}</p>
          </div>
        </div>
      </div>

      <div>
        <h2 class="text-sm font-semibold text-slate-700 mb-2">Group stage scoring</h2>
        <p class="text-xs text-slate-500 mb-2">Leave blank to use the knockout scoring above for group matches.</p>
        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="label" for="group_points_to_win">Points / set</label>
            <input id="group_points_to_win" v-model.number="form.group_points_to_win" type="number" min="1" class="input" />
            <p v-if="form.errors.group_points_to_win" class="text-sm text-red-600 mt-1">{{ form.errors.group_points_to_win }}</p>
          </div>
          <div>
            <label class="label" for="group_sets_to_win">Sets to win</label>
            <input id="group_sets_to_win" v-model.number="form.group_sets_to_win" type="number" min="1" class="input" />
            <p v-if="form.errors.group_sets_to_win" class="text-sm text-red-600 mt-1">{{ form.errors.group_sets_to_win }}</p>
          </div>
          <div>
            <label class="label" for="group_deuce_cap">Deuce cap</label>
            <input id="group_deuce_cap" v-model.number="form.group_deuce_cap" type="number" min="1" class="input" />
            <p v-if="form.errors.group_deuce_cap" class="text-sm text-red-600 mt-1">{{ form.errors.group_deuce_cap }}</p>
          </div>
        </div>
      </div>

      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="label" for="start_date">Start date</label>
          <input id="start_date" v-model="form.start_date" type="date" class="input" />
          <p v-if="form.errors.start_date" class="text-sm text-red-600 mt-1">{{ form.errors.start_date }}</p>
        </div>
        <div>
          <label class="label" for="end_date">End date</label>
          <input id="end_date" v-model="form.end_date" type="date" class="input" />
          <p v-if="form.errors.end_date" class="text-sm text-red-600 mt-1">{{ form.errors.end_date }}</p>
        </div>
      </div>

      <div class="flex items-center gap-3 pt-2">
        <button type="submit" class="btn-primary text-sm" :disabled="form.processing">Save changes</button>
        <Link :href="route('admin.tournaments.index')" class="btn-secondary text-sm">Cancel</Link>
      </div>
    </form>
  </AdminLayout>
</template>
