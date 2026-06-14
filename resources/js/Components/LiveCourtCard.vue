<script setup lang="ts">
import { computed } from 'vue';

interface TeamRef {
  id: string;
  displayName: string;
}

interface SetScore {
  setNumber: number;
  teamAScore: number;
  teamBScore: number;
  winnerId: string | null;
}

interface MatchDetail {
  id: string;
  status: string;
  stage: string;
  categoryName?: string | null;
  categoryType?: string | null;
  scheduledAt?: string | null;
  teamA?: TeamRef | null;
  teamB?: TeamRef | null;
  sets: SetScore[];
}

interface LiveCourtCardData {
  court: { id: string; name: string };
  current: MatchDetail | null;
  next: MatchDetail | null;
}

const props = defineProps<{
  card: LiveCourtCardData;
  /** Label shown when the court is currently live. Default 'Live'. */
  liveLabel?: string;
  /** Label shown when there is an upcoming match but none in progress. Default 'Up next'. */
  upNextLabel?: string;
  /** Label shown when there is no current or next match. Default 'Idle'. */
  idleLabel?: string;
  /** Label for the sets summary header. Default 'Sets'. */
  setsLabel?: string;
  /** Label for empty state. Default 'No upcoming match'. */
  noUpcomingLabel?: string;
  /** Label shown when next match has no scheduled time. Default 'Awaiting start'. */
  awaitingStartLabel?: string;
}>();

const liveLabel = computed(() => props.liveLabel ?? 'Live');
const upNextLabel = computed(() => props.upNextLabel ?? 'Up next');
const idleLabel = computed(() => props.idleLabel ?? 'Idle');
const setsLabel = computed(() => props.setsLabel ?? 'Sets');
const noUpcomingLabel = computed(() => props.noUpcomingLabel ?? 'No upcoming match');
const awaitingStartLabel = computed(() => props.awaitingStartLabel ?? 'Awaiting start');

const m = computed<MatchDetail | null>(() => props.card.current ?? props.card.next ?? null);
const isLive = computed(() => props.card.current?.status === 'IN_PROGRESS');

const liveSet = computed(() => {
  const match = props.card.current;
  if (!match) return null;
  for (const s of match.sets) if (s.winnerId == null) return s;
  return match.sets[match.sets.length - 1] ?? null;
});

const setsWonA = computed(() =>
  (m.value?.sets ?? []).filter((s) => s.winnerId === m.value?.teamA?.id).length,
);
const setsWonB = computed(() =>
  (m.value?.sets ?? []).filter((s) => s.winnerId === m.value?.teamB?.id).length,
);

function formatTime(iso: string | null | undefined) {
  if (!iso) return null;
  return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
  <article
    class="relative overflow-hidden rounded-2xl ring-1 transition"
    :class="
      isLive
        ? 'bg-slate-900 text-white ring-live-500/40 shadow-xl'
        : card.next
          ? 'bg-white ring-slate-200'
          : 'bg-slate-50 ring-slate-200'
    "
  >
    <!-- Header -->
    <header class="flex items-center justify-between px-5 pt-4">
      <div class="flex items-center gap-2">
        <span
          class="text-display text-3xl font-bold leading-none"
          :class="isLive ? 'text-white' : 'text-slate-900'"
        >
          {{ card.court.name }}
        </span>
      </div>
      <div class="flex items-center gap-2">
        <span v-if="isLive" class="live-dot"></span>
        <span
          :class="
            isLive
              ? 'chip bg-live-500/20 text-live-400 ring-live-500/30'
              : card.next
                ? 'chip-soon'
                : 'chip-done'
          "
        >
          {{ isLive ? liveLabel : card.next ? upNextLabel : idleLabel }}
        </span>
      </div>
    </header>

    <!-- Body -->
    <div v-if="m" class="px-5 pb-5 pt-3">
      <div
        class="text-xs uppercase tracking-widest mb-3"
        :class="isLive ? 'text-slate-400' : 'text-slate-500'"
      >
        {{ m.categoryName || m.categoryType }} · {{ m.stage }}
      </div>

      <!-- Team A -->
      <div class="flex items-baseline justify-between gap-2">
        <span
          class="font-semibold truncate text-lg"
          :class="isLive ? 'text-white' : 'text-slate-900'"
        >
          {{ m.teamA?.displayName || '—' }}
        </span>
        <span
          v-if="liveSet"
          class="text-display text-6xl tabular-nums leading-none"
          :class="isLive ? 'text-white' : 'text-slate-900'"
        >
          {{ liveSet.teamAScore }}
        </span>
        <span v-else class="text-slate-500 text-sm">—</span>
      </div>
      <!-- Team B -->
      <div class="flex items-baseline justify-between gap-2 mt-1">
        <span
          class="font-semibold truncate text-lg"
          :class="isLive ? 'text-white' : 'text-slate-900'"
        >
          {{ m.teamB?.displayName || '—' }}
        </span>
        <span
          v-if="liveSet"
          class="text-display text-6xl tabular-nums leading-none"
          :class="isLive ? 'text-white' : 'text-slate-900'"
        >
          {{ liveSet.teamBScore }}
        </span>
        <span v-else class="text-slate-500 text-sm">—</span>
      </div>

      <!-- Set summary -->
      <div
        v-if="m.sets.length"
        class="mt-4 flex items-center gap-2 text-xs font-mono"
        :class="isLive ? 'text-slate-400' : 'text-slate-500'"
      >
        <span class="uppercase tracking-wider opacity-75">{{ setsLabel }}</span>
        <span
          v-for="s in m.sets"
          :key="s.setNumber"
          class="px-2 py-0.5 rounded ring-1"
          :class="isLive ? 'ring-slate-700 bg-slate-800/60' : 'ring-slate-200 bg-white'"
        >
          {{ s.teamAScore }}–{{ s.teamBScore }}<span v-if="s.winnerId">✓</span>
        </span>
        <span class="ml-auto" :class="isLive ? 'text-white' : 'text-slate-700'">
          {{ setsWonA }} – {{ setsWonB }}
        </span>
      </div>

      <!-- Next match info -->
      <div
        v-if="!isLive && card.next"
        class="mt-3 flex items-center justify-between text-xs"
        :class="isLive ? 'text-slate-400' : 'text-slate-500'"
      >
        <span v-if="formatTime(card.next.scheduledAt)" class="font-medium">
          ⏱ {{ formatTime(card.next.scheduledAt) }}
        </span>
        <span v-else>{{ awaitingStartLabel }}</span>
      </div>
    </div>
    <div v-else class="px-5 py-8 text-center text-slate-400 text-sm">{{ noUpcomingLabel }}</div>
  </article>
</template>
