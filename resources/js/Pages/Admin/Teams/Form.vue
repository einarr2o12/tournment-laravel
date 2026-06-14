<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
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
  display_name: string;
  seed: number | null;
  players: TeamPlayer[];
  playerIds: string[];
};

type CategoryOption = {
  id: string;
  name: string;
  type?: string | null;
  tournamentName?: string | null;
};

type PlayerOption = {
  id: string;
  full_name: string;
};

const props = defineProps<{
  // null = create mode; present = edit mode.
  team: Team | null;
  categories: CategoryOption[];
  players: PlayerOption[];
}>();

const isEdit = computed(() => props.team !== null);

// The two player selects are driven by separate refs (position 1 & 2) so the
// UI reads naturally. On submit they're compiled into the ordered `player_ids`
// array the controller expects (index order = team_players.position, 1-based).
// An empty position 2 yields a singles team (1 player).
const player1Id = ref<string>(props.team?.playerIds?.[0] ?? props.players[0]?.id ?? '');
const player2Id = ref<string>(props.team?.playerIds?.[1] ?? '');

// useForm keys are snake_case — they match the FormRequest rules() directly.
const form = useForm<{
  category_id: string;
  display_name: string;
  seed: number | null;
  player_ids: string[];
}>({
  category_id: props.team?.category_id ?? props.categories[0]?.id ?? '',
  display_name: props.team?.display_name ?? '',
  seed: props.team?.seed ?? null,
  player_ids: props.team?.playerIds ?? [],
});

// Position 2's options exclude whoever is picked for position 1, so the
// `distinct` rule can never be violated from the UI.
const player2Options = computed(() =>
  props.players.filter((p) => p.id !== player1Id.value),
);

// If position 1 ends up equal to position 2 (e.g. after a change), clear 2.
watch(player1Id, (id) => {
  if (id && id === player2Id.value) {
    player2Id.value = '';
  }
});

function categoryLabel(c: CategoryOption): string {
  const parts = [c.name];
  if (c.tournamentName) parts.push(`(${c.tournamentName})`);
  return parts.join(' ');
}

function submit() {
  // Compile ordered, de-duplicated, non-empty ids. Position is array order.
  const ids: string[] = [];
  for (const id of [player1Id.value, player2Id.value]) {
    if (id && !ids.includes(id)) ids.push(id);
  }
  form.player_ids = ids;

  if (isEdit.value && props.team) {
    form.put(route('admin.teams.update', { team: props.team.id }));
  } else {
    form.post(route('admin.teams.store'));
  }
}
</script>

<template>
  <Head :title="isEdit ? 'Edit team' : 'New team'" />
  <AdminLayout>
    <div class="flex items-center gap-3 mb-5">
      <Link :href="route('admin.teams.index')" class="text-sm font-medium text-slate-500 hover:text-brand-700">
        ← Teams
      </Link>
    </div>

    <h1 class="text-2xl font-bold text-slate-900 mb-5">
      {{ isEdit ? 'Edit team' : 'New team' }}
    </h1>

    <form class="card max-w-xl space-y-5" @submit.prevent="submit">
      <div>
        <label class="label" for="category_id">Category</label>
        <select id="category_id" v-model="form.category_id" class="input">
          <option v-if="!props.categories.length" value="" disabled>No categories available</option>
          <option v-for="c in props.categories" :key="c.id" :value="c.id">{{ categoryLabel(c) }}</option>
        </select>
        <p v-if="form.errors.category_id" class="text-sm text-red-600 mt-1">{{ form.errors.category_id }}</p>
      </div>

      <div>
        <label class="label" for="display_name">Display name</label>
        <input id="display_name" v-model="form.display_name" type="text" class="input" placeholder="Smith / Jones" />
        <p v-if="form.errors.display_name" class="text-sm text-red-600 mt-1">{{ form.errors.display_name }}</p>
      </div>

      <div>
        <label class="label" for="seed">Seed <span class="font-normal text-slate-400">(optional)</span></label>
        <input id="seed" v-model.number="form.seed" type="number" min="1" class="input" placeholder="e.g. 1" />
        <p v-if="form.errors.seed" class="text-sm text-red-600 mt-1">{{ form.errors.seed }}</p>
      </div>

      <fieldset class="space-y-4 border-t border-slate-100 pt-4">
        <legend class="text-sm font-semibold text-slate-700">Players</legend>

        <div>
          <label class="label" for="player1">Player 1 <span class="font-normal text-slate-400">(position 1)</span></label>
          <select id="player1" v-model="player1Id" class="input">
            <option value="" disabled>Select a player…</option>
            <option v-for="p in props.players" :key="p.id" :value="p.id">{{ p.full_name }}</option>
          </select>
          <!-- Server validates the compiled player_ids array. -->
          <p v-if="form.errors.player_ids" class="text-sm text-red-600 mt-1">{{ form.errors.player_ids }}</p>
          <p v-if="(form.errors as Record<string, string>)['player_ids.0']" class="text-sm text-red-600 mt-1">
            {{ (form.errors as Record<string, string>)['player_ids.0'] }}
          </p>
        </div>

        <div>
          <label class="label" for="player2">Player 2 <span class="font-normal text-slate-400">(position 2 — doubles only)</span></label>
          <select id="player2" v-model="player2Id" class="input">
            <option value="">— None (singles) —</option>
            <option v-for="p in player2Options" :key="p.id" :value="p.id">{{ p.full_name }}</option>
          </select>
          <p v-if="(form.errors as Record<string, string>)['player_ids.1']" class="text-sm text-red-600 mt-1">
            {{ (form.errors as Record<string, string>)['player_ids.1'] }}
          </p>
        </div>
      </fieldset>

      <div class="flex items-center gap-3 pt-2">
        <button type="submit" class="btn-primary text-sm" :disabled="form.processing">
          {{ isEdit ? 'Save changes' : 'Create team' }}
        </button>
        <Link :href="route('admin.teams.index')" class="btn-secondary text-sm">Cancel</Link>
      </div>
    </form>
  </AdminLayout>
</template>
