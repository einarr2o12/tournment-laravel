<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

type Player = {
  id: string;
  tournament_id: string;
  full_name: string;
  gender: string | null;
  club: string | null;
  contact: string | null;
};

type TournamentOption = {
  id: string;
  name: string;
};

const props = defineProps<{
  // null = create mode; present = edit mode.
  player: Player | null;
  tournaments: TournamentOption[];
  genders: Record<string, string>;
}>();

const isEdit = computed(() => props.player !== null);

const genderEntries = computed(() => Object.entries(props.genders));

// useForm field keys are snake_case — they match the FormRequest rules() and
// the controller's $request->validated() directly (no transform needed).
const form = useForm({
  tournament_id: props.player?.tournament_id ?? props.tournaments[0]?.id ?? '',
  full_name: props.player?.full_name ?? '',
  gender: props.player?.gender ?? genderEntries.value[0]?.[0] ?? '',
  club: props.player?.club ?? '',
  contact: props.player?.contact ?? '',
});

function submit() {
  if (isEdit.value && props.player) {
    form.put(route('admin.players.update', { player: props.player.id }));
  } else {
    form.post(route('admin.players.store'));
  }
}
</script>

<template>
  <Head :title="isEdit ? 'Edit player' : 'New player'" />
  <AdminLayout>
    <div class="flex items-center gap-3 mb-5">
      <Link :href="route('admin.players.index')" class="text-sm font-medium text-slate-500 hover:text-brand-700">
        ← Players
      </Link>
    </div>

    <h1 class="text-2xl font-bold text-slate-900 mb-5">
      {{ isEdit ? 'Edit player' : 'New player' }}
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
        <label class="label" for="full_name">Full name</label>
        <input id="full_name" v-model="form.full_name" type="text" class="input" placeholder="Lin Htet Aung" />
        <p v-if="form.errors.full_name" class="text-sm text-red-600 mt-1">{{ form.errors.full_name }}</p>
      </div>

      <div>
        <label class="label" for="gender">Gender</label>
        <select id="gender" v-model="form.gender" class="input">
          <option v-for="[value, label] in genderEntries" :key="value" :value="value">{{ label }}</option>
        </select>
        <p v-if="form.errors.gender" class="text-sm text-red-600 mt-1">{{ form.errors.gender }}</p>
      </div>

      <div>
        <label class="label" for="club">Club</label>
        <input id="club" v-model="form.club" type="text" class="input" placeholder="Yangon BC" />
        <p v-if="form.errors.club" class="text-sm text-red-600 mt-1">{{ form.errors.club }}</p>
      </div>

      <div>
        <label class="label" for="contact">Contact</label>
        <input id="contact" v-model="form.contact" type="text" class="input" placeholder="09… / email (optional)" />
        <p v-if="form.errors.contact" class="text-sm text-red-600 mt-1">{{ form.errors.contact }}</p>
      </div>

      <div class="flex items-center gap-3 pt-2">
        <button type="submit" class="btn-primary text-sm" :disabled="form.processing">
          {{ isEdit ? 'Save changes' : 'Create player' }}
        </button>
        <Link :href="route('admin.players.index')" class="btn-secondary text-sm">Cancel</Link>
      </div>
    </form>
  </AdminLayout>
</template>
