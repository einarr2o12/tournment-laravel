<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import BracketTree from '../../Components/Public/BracketTree.vue';
import StandingsTable from '../../Components/Public/StandingsTable.vue';

interface BracketSet {
  teamAScore: number;
  teamBScore: number;
}
interface BracketTeam {
  id: string;
  displayName: string;
  seed?: number | null;
}
interface BracketMatch {
  id: string;
  status: string;
  stage?: string | null;
  bracketSlot?: number | null;
  teamA?: BracketTeam | null;
  teamB?: BracketTeam | null;
  teamASource?: string | null;
  teamBSource?: string | null;
  winnerId?: string | null;
  sets: BracketSet[];
}
interface StandingRow {
  teamId: string;
  teamName: string;
  played: number;
  won: number;
  lost: number;
  setsFor: number;
  setsAgainst: number;
  pointsFor: number;
  pointsAgainst: number;
}
interface GroupStanding {
  groupId: string;
  categoryId: string;
  name: string;
  rows: StandingRow[];
}

defineProps<{
  tournament: { id: string; name: string };
  category: { id: string; name: string; code: string };
  bracket: BracketMatch[];
  standings: GroupStanding[];
}>();
</script>

<template>
  <Head :title="`${category.code} · ${category.name}`" />
  <div class="min-h-screen bg-[var(--color-bwf-page)] text-[var(--color-bwf-text)]">
    <!-- header -->
    <header class="border-b bwf-hairline">
      <div class="mx-auto flex max-w-6xl items-center px-4 py-4 sm:px-6">
        <Link
          :href="route('public.tournament.show', { tournament: tournament.id })"
          class="inline-flex items-center gap-1.5 text-sm text-[var(--color-bwf-text-2)] transition hover:text-[var(--color-bwf-text)]"
        >
          <span aria-hidden="true">←</span> {{ tournament.name }}
        </Link>
      </div>
    </header>

    <main class="mx-auto max-w-6xl space-y-8 px-4 py-6 sm:px-6">
      <!-- title -->
      <div class="flex items-center gap-3">
        <span class="bwf-draw-code text-base">{{ category.code }}</span>
        <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ category.name }}</h1>
      </div>

      <!-- group standings -->
      <section v-if="standings.length" class="space-y-3">
        <h2 class="bwf-label text-[var(--color-bwf-text-2)]">Group standings</h2>
        <div class="grid gap-4 lg:grid-cols-2">
          <StandingsTable
            v-for="g in standings"
            :key="g.groupId"
            :name="g.name"
            :rows="g.rows"
            show-sets
            :qualify-count="2"
          />
        </div>
      </section>

      <!-- knockout bracket -->
      <section class="space-y-3">
        <h2 class="bwf-label text-[var(--color-bwf-text-2)]">Knockout</h2>
        <BracketTree v-if="bracket.length" :matches="bracket" />
        <div
          v-else
          class="bwf-surface px-4 py-10 text-center text-sm text-[var(--color-bwf-muted)]"
        >
          Knockout bracket appears once the group seeds are resolved.
        </div>
      </section>
    </main>
  </div>
</template>
