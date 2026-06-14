<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

type Team = {
  id: string;
  displayName?: string | null;
};

type SetScore = {
  setNumber: number;
  teamAScore: number;
  teamBScore: number;
  winnerId?: string | null;
};

type MatchDetail = {
  id: string;
  status: string;
  categoryName?: string | null;
  stage?: string | null;
  teamA?: Team | null;
  teamB?: Team | null;
  sets: SetScore[];
};

type Court = {
  id: string;
  name: string;
  tournamentName?: string | null;
};

type RefereeCourt = {
  court: Court;
  current: MatchDetail | null;
  next: MatchDetail | null;
  queue: MatchDetail[];
};

type User = {
  id: string;
  username?: string | null;
  fullName?: string | null;
};

const props = defineProps<{
  courts: RefereeCourt[];
  auth: { user: User | null };
}>();

function logout() {
  router.post(route('logout'));
}
</script>

<template>
  <Head title="Referee" />
  <div class="min-h-screen">
    <header class="bg-white border-b border-slate-200">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <Link :href="route('public.index')" class="text-lg font-semibold text-brand-700">
            🏸 Tournament
          </Link>
          <span class="text-xs uppercase tracking-wider text-slate-400 border-l border-slate-200 pl-3 hidden sm:inline">
            Referee
          </span>
        </div>
        <div class="flex items-center gap-3">
          <span class="text-sm text-slate-600 hidden sm:inline">
            {{ props.auth.user?.fullName || props.auth.user?.username }}
          </span>
          <button class="btn-secondary" @click="logout">Logout</button>
        </div>
      </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 sm:px-6 py-6">
      <h1 class="text-2xl font-bold mb-2">Your courts</h1>
      <p class="text-slate-500 mb-6">Tap a court to start scoring.</p>

      <div v-if="props.courts.length === 0" class="card text-center">
        <div class="text-4xl mb-2">🏸</div>
        <p class="text-slate-500">No active courts right now.</p>
        <p class="text-sm text-slate-400 mt-1">Courts appear here once a tournament is scheduled or live.</p>
      </div>
      <div v-else class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <Link
          v-for="c in props.courts"
          :key="c.court.id"
          :href="route('referee.courts.show', { court: c.court.id })"
          class="card hover:ring-brand-400 hover:ring-2 transition cursor-pointer"
        >
          <div class="flex items-start justify-between">
            <div>
              <div class="text-xs uppercase tracking-wider text-slate-400">
                {{ c.court.tournamentName }}
              </div>
              <h2 class="text-xl font-semibold text-slate-900 mt-0.5">{{ c.court.name }}</h2>
            </div>
            <span
              class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium ring-1 ring-inset"
              :class="
                c.current
                  ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                  : c.next
                    ? 'bg-blue-50 text-blue-700 ring-blue-200'
                    : 'bg-slate-100 text-slate-500 ring-slate-200'
              "
            >
              {{ c.current ? 'LIVE' : c.next ? 'UP NEXT' : 'IDLE' }}
            </span>
          </div>
          <div v-if="c.current" class="mt-4 text-sm">
            <div class="font-medium text-slate-800">
              {{ c.current.teamA?.displayName || 'TBD' }}
            </div>
            <div class="text-slate-400">vs</div>
            <div class="font-medium text-slate-800">
              {{ c.current.teamB?.displayName || 'TBD' }}
            </div>
            <div v-if="c.current.sets.length > 0" class="mt-2 font-mono text-xs text-slate-600">
              {{ c.current.sets.map(s => `${s.teamAScore}-${s.teamBScore}`).join(' / ') }}
            </div>
          </div>
          <div v-else-if="c.next" class="mt-4 text-sm text-slate-600">
            Next: {{ c.next.teamA?.displayName || 'TBD' }} vs {{ c.next.teamB?.displayName || 'TBD' }}
          </div>
        </Link>
      </div>
    </main>
  </div>
</template>
