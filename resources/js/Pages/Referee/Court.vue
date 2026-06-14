<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { connectEcho } from '../../bootstrap';

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
  card: RefereeCourt;
  auth: { user: User | null };
}>();

const submitting = ref(false);

const match = computed<MatchDetail | null>(
  () => props.card?.current ?? props.card?.next ?? null,
);
const isLive = computed(() => match.value?.status === 'IN_PROGRESS');

const currentSetIndex = computed(() => {
  const sets = match.value?.sets ?? [];
  for (let i = 0; i < sets.length; i++) {
    if (sets[i].winnerId == null) return i;
  }
  return sets.length - 1;
});

const liveSetScores = computed(() => {
  const sets = match.value?.sets ?? [];
  const idx = currentSetIndex.value;
  return sets[idx] ?? { teamAScore: 0, teamBScore: 0 };
});

const setsWonA = computed(() =>
  (match.value?.sets ?? []).filter((s) => s.winnerId === match.value?.teamA?.id).length,
);
const setsWonB = computed(() =>
  (match.value?.sets ?? []).filter((s) => s.winnerId === match.value?.teamB?.id).length,
);

function reload() {
  router.reload({ only: ['card'] });
}

function start() {
  if (!match.value || submitting.value) return;
  submitting.value = true;
  router.post(
    route('referee.matches.start', { match: match.value.id }),
    {},
    {
      preserveScroll: true,
      onFinish: () => {
        submitting.value = false;
      },
    },
  );
}

function point(teamId: string | null | undefined) {
  if (!match.value || !teamId || submitting.value || !isLive.value) return;
  submitting.value = true;
  router.post(
    route('referee.matches.point', { match: match.value.id }),
    { scoringTeamId: teamId },
    {
      preserveScroll: true,
      onFinish: () => {
        submitting.value = false;
      },
    },
  );
}

function undo() {
  if (!match.value || submitting.value) return;
  submitting.value = true;
  router.post(
    route('referee.matches.undo', { match: match.value.id }),
    {},
    {
      preserveScroll: true,
      onFinish: () => {
        submitting.value = false;
      },
    },
  );
}

function declareWalkover(teamId: string) {
  if (!match.value) return;
  if (!confirm('Declare walkover for the other team to forfeit?')) return;
  router.post(
    route('referee.matches.walkover', { match: match.value.id }),
    { winnerTeamId: teamId },
    { preserveScroll: true },
  );
}

// Subscribe to court channel for sync via Laravel Echo (window.Echo).
// Falls back to polling when Echo is unavailable (e.g. phone via ngrok
// can't reach ws://localhost:8080, so connectEcho() returns null).
let echoChannel: { stopListening?: (event: string) => void } | null = null;
let pollTimer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
  const echo = connectEcho();
  const w = window as unknown as {
    Echo?: {
      channel: (name: string) => {
        listen: (event: string, cb: (payload: unknown) => void) => unknown;
        stopListening?: (event: string) => void;
      };
    };
  };
  if (echo && w.Echo && props.card?.court?.id) {
    const ch = w.Echo.channel(`court.${props.card.court.id}`);
    ch.listen('.match.updated', () => reload());
    ch.listen('.court.updated', () => reload());
    echoChannel = ch as { stopListening?: (event: string) => void };
  } else {
    pollTimer = setInterval(() => {
      if (!document.hidden) reload();
    }, 5000);
  }
});

onUnmounted(() => {
  if (pollTimer !== null) {
    clearInterval(pollTimer);
    pollTimer = null;
  }
  const w = window as unknown as {
    Echo?: { leave: (name: string) => void };
  };
  if (w.Echo && props.card?.court?.id) {
    w.Echo.leave(`court.${props.card.court.id}`);
  }
  if (echoChannel?.stopListening) {
    echoChannel.stopListening('.match.updated');
    echoChannel.stopListening('.court.updated');
  }
});
</script>

<template>
  <Head :title="props.card?.court?.name ?? 'Court'" />
  <div class="min-h-screen flex flex-col bg-slate-900 text-white">
    <header class="bg-slate-800 border-b border-slate-700 px-4 sm:px-6 py-3 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <Link :href="route('referee.dashboard')" class="text-slate-400 hover:text-white">
          ← Courts
        </Link>
        <span class="text-lg font-semibold">{{ props.card?.court.name ?? 'Court' }}</span>
        <span class="text-xs text-slate-400 hidden sm:inline">
          {{ props.card?.court.tournamentName }}
        </span>
      </div>
      <span v-if="props.auth.user" class="text-sm text-slate-400">
        {{ props.auth.user.username }}
      </span>
    </header>

    <div v-if="!match" class="flex-1 flex flex-col items-center justify-center text-center px-4">
      <div class="text-5xl mb-3">🏸</div>
      <p class="text-slate-300">No match on this court right now.</p>
      <button class="btn-secondary mt-4" @click="reload">Refresh</button>
    </div>

    <main v-else class="flex-1 flex flex-col relative">
      <!-- Match info -->
      <div class="px-4 sm:px-6 py-3 border-b border-slate-700 flex items-center justify-between text-sm">
        <div>
          <span class="text-slate-400 mr-2">{{ match.categoryName }}</span>
          <span class="text-slate-300">{{ match.stage }}</span>
        </div>
        <div class="flex items-center gap-2">
          <span
            class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium"
            :class="isLive ? 'bg-emerald-500/20 text-emerald-300' : 'bg-blue-500/20 text-blue-300'"
          >
            {{ match.status }}
          </span>
        </div>
      </div>

      <!-- Match queue -->
      <div
        v-if="props.card && props.card.queue.length > 0"
        class="px-4 sm:px-6 py-2 border-b border-slate-700 text-xs text-slate-400 flex items-center gap-2 overflow-x-auto"
      >
        <span class="font-medium uppercase tracking-wider shrink-0">Queue:</span>
        <span
          v-for="m in props.card.queue.slice(0, 4)"
          :key="m.id"
          class="px-2 py-1 rounded bg-slate-800 ring-1 ring-slate-700 whitespace-nowrap"
        >
          {{ m.teamA?.displayName || 'TBD' }} vs {{ m.teamB?.displayName || 'TBD' }}
        </span>
        <span v-if="props.card.queue.length > 4" class="text-slate-500">
          +{{ props.card.queue.length - 4 }} more
        </span>
      </div>

      <!-- Set summary -->
      <div
        v-if="match.sets.length > 0"
        class="px-4 sm:px-6 py-2 text-xs font-mono text-slate-400 border-b border-slate-700"
      >
        Sets:
        <span v-for="s in match.sets" :key="s.setNumber" class="ml-2">
          [{{ s.teamAScore }}-{{ s.teamBScore }}{{ s.winnerId ? '✓' : '' }}]
        </span>
        <span class="ml-3 text-slate-300">— {{ setsWonA }} vs {{ setsWonB }}</span>
      </div>

      <!-- Big tap targets -->
      <div class="flex-1 grid grid-cols-2 relative">
        <button
          class="flex flex-col items-center justify-center text-white bg-blue-600 hover:bg-blue-500 active:bg-blue-700 disabled:opacity-50 transition p-6"
          :disabled="!isLive || submitting"
          @click="point(match.teamA?.id)"
        >
          <div class="text-sm uppercase tracking-wider opacity-90 mb-2">
            {{ match.teamA?.displayName || 'TBD' }}
          </div>
          <div class="font-mono text-[18vh] sm:text-[22vh] leading-none">
            {{ liveSetScores.teamAScore }}
          </div>
          <div class="text-xs opacity-80 mt-3">Tap to score</div>
        </button>
        <button
          class="flex flex-col items-center justify-center text-white bg-rose-600 hover:bg-rose-500 active:bg-rose-700 disabled:opacity-50 transition p-6"
          :disabled="!isLive || submitting"
          @click="point(match.teamB?.id)"
        >
          <div class="text-sm uppercase tracking-wider opacity-90 mb-2">
            {{ match.teamB?.displayName || 'TBD' }}
          </div>
          <div class="font-mono text-[18vh] sm:text-[22vh] leading-none">
            {{ liveSetScores.teamBScore }}
          </div>
          <div class="text-xs opacity-80 mt-3">Tap to score</div>
        </button>

        <!-- Start overlay -->
        <div
          v-if="match.status === 'SCHEDULED'"
          class="absolute inset-0 flex flex-col items-center justify-center bg-slate-900/85 backdrop-blur-sm gap-6 z-10"
        >
          <div class="text-center">
            <div class="text-sm uppercase tracking-wider text-slate-400 mb-1">
              {{ match.categoryName }} · {{ match.stage }}
            </div>
            <div class="text-2xl font-semibold text-white">
              {{ match.teamA?.displayName || 'TBD' }}
              <span class="text-slate-500 mx-2">vs</span>
              {{ match.teamB?.displayName || 'TBD' }}
            </div>
          </div>
          <button
            class="px-12 py-5 rounded-xl bg-emerald-500 hover:bg-emerald-400 active:bg-emerald-600 text-white text-2xl font-semibold shadow-2xl disabled:opacity-60 transition"
            :disabled="submitting || !match.teamA || !match.teamB"
            @click="start"
          >
            ▶ Start match
          </button>
          <p v-if="!match.teamA || !match.teamB" class="text-sm text-amber-300">
            Waiting for opponents to be assigned.
          </p>
        </div>
      </div>

      <!-- Action bar -->
      <div class="border-t border-slate-700 bg-slate-800 px-4 sm:px-6 py-3 flex items-center justify-between gap-2">
        <button class="btn-secondary" :disabled="!isLive || submitting" @click="undo">
          ↶ Undo
        </button>
        <div class="flex items-center gap-2">
          <button
            v-if="isLive && match.teamA"
            class="btn-secondary text-amber-700"
            @click="declareWalkover(match.teamA.id)"
          >
            {{ match.teamA?.displayName }} wins (W/O)
          </button>
          <button
            v-if="isLive && match.teamB"
            class="btn-secondary text-amber-700"
            @click="declareWalkover(match.teamB.id)"
          >
            {{ match.teamB?.displayName }} wins (W/O)
          </button>
        </div>
      </div>
    </main>
  </div>
</template>
