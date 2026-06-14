<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { connectEcho } from '../../bootstrap';

interface Team {
  id: string;
  displayName: string;
}
interface Court {
  id: string;
  name: string;
}
interface MatchSet {
  teamAScore: number;
  teamBScore: number;
}
interface MatchDetail {
  id: string;
  status: 'SCHEDULED' | 'IN_PROGRESS' | 'COMPLETED' | 'WALKOVER' | string;
  stage?: string | null;
  scheduledAt?: string | null;
  categoryId?: string | null;
  categoryName?: string | null;
  categoryType?: string | null;
  teamA?: Team | null;
  teamB?: Team | null;
  court?: Court | null;
  winnerId?: string | null;
  sets: MatchSet[];
  // Knockout bracket fields (present on SEMIFINAL/FINAL/THIRD_PLACE rows)
  roundNumber?: number | null;
  bracketSlot?: number | null;
  nextMatchId?: string | null;
  loserNextMatchId?: string | null;
  teamASource?: string | null;
  teamBSource?: string | null;
}
interface LiveCourtCard {
  court: Court;
  current: MatchDetail | null;
  next: MatchDetail | null;
}
interface Category {
  id: string;
  name: string;
  type: string;
}
interface TournamentDetail {
  id: string;
  name: string;
  status: string;
  format: string;
  venue?: string | null;
  startDate?: string | null;
  endDate?: string | null;
  categories: Category[];
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

declare global {
  interface Window {
    Echo?: {
      channel: (name: string) => {
        listen: (event: string, cb: (e: { match: MatchDetail }) => void) => void;
      };
      leaveChannel: (name: string) => void;
    };
  }
}

const props = defineProps<{
  tournament: TournamentDetail;
  courts: Court[];
  live: LiveCourtCard[];
  matches: MatchDetail[];
  standings: GroupStanding[];
}>();

const tournament = ref<TournamentDetail>(props.tournament);
const live = ref<LiveCourtCard[]>([...props.live]);
const matches = ref<MatchDetail[]>([...props.matches]);
const standings = ref<GroupStanding[]>([...props.standings]);

// Partial reloads (polling fallback) update props but NOT the local refs
// copied above — sync them so polled data actually refreshes the UI.
watch(() => props.live, (v) => { live.value = [...v]; });
watch(() => props.matches, (v) => { matches.value = [...v]; });
watch(() => props.standings, (v) => { standings.value = [...v]; });

const selectedCategoryId = ref<string | null>(
  props.tournament.categories.length > 0 ? props.tournament.categories[0].id : null,
);
const tab = ref<'schedule' | 'bracket' | 'standings'>('schedule');

function applyMatchUpdate(updated: MatchDetail) {
  const i = matches.value.findIndex((m) => m.id === updated.id);
  if (i >= 0) matches.value[i] = updated;
  else matches.value.push(updated);

  for (const card of live.value) {
    if (card.court.id === updated.court?.id) {
      if (updated.status === 'IN_PROGRESS') {
        card.current = updated;
      } else if (updated.status === 'COMPLETED' || updated.status === 'WALKOVER') {
        if (card.current?.id === updated.id) card.current = null;
      } else if (card.current?.id === updated.id) {
        card.current = updated;
      }
    }
  }
}

const liveCards = computed(() =>
  [...live.value].sort((a, b) => {
    const score = (c: LiveCourtCard) => (c.current ? 0 : c.next ? 1 : 2);
    return score(a) - score(b);
  }),
);
const liveCount = computed(() => live.value.filter((c) => c.current).length);

const upcomingMatches = computed(() => {
  return matches.value
    .filter((m) => m.status === 'SCHEDULED' && m.teamA && m.teamB)
    .sort((a, b) => {
      const ta = a.scheduledAt ? new Date(a.scheduledAt).getTime() : Number.MAX_SAFE_INTEGER;
      const tb = b.scheduledAt ? new Date(b.scheduledAt).getTime() : Number.MAX_SAFE_INTEGER;
      return ta - tb;
    })
    .slice(0, 8);
});

const matchesForCategory = computed(() => {
  if (!selectedCategoryId.value) return matches.value;
  return matches.value.filter((m) => m.categoryId === selectedCategoryId.value);
});

const standingsForCategory = computed(() => {
  if (!selectedCategoryId.value) return standings.value;
  return standings.value.filter((g) => g.categoryId === selectedCategoryId.value);
});

// ---- Knockout bracket ----------------------------------------------------
const KNOCKOUT_STAGES = new Set(['SEMIFINAL', 'FINAL', 'THIRD_PLACE']);

// Turn a source code (team_a_source/team_b_source) into a short, human label.
//  G:A:1 -> A1   G:B:2 -> B2   (group-letter + position)
//  G::1  -> 1st  G::4 -> 4th   (single-group position)
//  W:1   -> Winner SF1         L:2 -> Loser SF2
function sourceLabel(code: string | null | undefined): string {
  if (!code) return 'TBD';
  const parts = code.split(':');
  const kind = parts[0];
  if (kind === 'G') {
    const grp = parts[1] ?? '';
    const pos = parts[2] ?? '';
    if (grp) return `${grp}${pos}`; // A1, B2
    const ord = ['', '1st', '2nd', '3rd', '4th'][Number(pos)] ?? `${pos}th`;
    return ord; // 1st .. 4th
  }
  if (kind === 'W') return `Winner SF${parts[1] ?? ''}`;
  if (kind === 'L') return `Loser SF${parts[1] ?? ''}`;
  return code;
}

const bracketMatches = computed<MatchDetail[]>(() =>
  matches.value
    .filter(
      (m) =>
        m.categoryId === selectedCategoryId.value &&
        m.stage != null &&
        KNOCKOUT_STAGES.has(m.stage),
    )
    .sort((a, b) => (a.bracketSlot ?? 99) - (b.bracketSlot ?? 99)),
);

function matchBySlot(slot: number): MatchDetail | null {
  return bracketMatches.value.find((m) => m.bracketSlot === slot) ?? null;
}
const sf1 = computed(() => matchBySlot(1));
const sf2 = computed(() => matchBySlot(2));
const finalMatch = computed(() => matchBySlot(3));
const bronzeMatch = computed(() => matchBySlot(4));

// The champion (final winner) display name, or null while pending.
const championName = computed(() => {
  const f = finalMatch.value;
  if (!f || !f.winnerId) return null;
  if (f.teamA?.id === f.winnerId) return f.teamA.displayName;
  if (f.teamB?.id === f.winnerId) return f.teamB.displayName;
  return null;
});

const hasBracket = computed(() => bracketMatches.value.length > 0);

const STAGE_LABEL: Record<string, string> = {
  SEMIFINAL: 'Semifinal',
  FINAL: 'Final',
  THIRD_PLACE: 'Bronze Final',
};

function bracketStatusChip(s: string) {
  if (s === 'IN_PROGRESS') return 'LIVE';
  if (s === 'COMPLETED' || s === 'WALKOVER') return 'DONE';
  return 'SCHEDULED';
}

const scheduleByDate = computed(() => {
  const groups = new Map<string, MatchDetail[]>();
  for (const m of matchesForCategory.value) {
    const key = m.scheduledAt
      ? new Date(m.scheduledAt).toLocaleDateString([], {
          weekday: 'short',
          month: 'short',
          day: 'numeric',
        })
      : 'Unscheduled';
    const arr = groups.get(key) ?? [];
    arr.push(m);
    groups.set(key, arr);
  }
  return Array.from(groups.entries()).map(([date, items]) => ({
    date,
    items: items.sort((a, b) => {
      const ta = a.scheduledAt ? new Date(a.scheduledAt).getTime() : Number.MAX_SAFE_INTEGER;
      const tb = b.scheduledAt ? new Date(b.scheduledAt).getTime() : Number.MAX_SAFE_INTEGER;
      return ta - tb;
    }),
  }));
});

function statusChipClass(s: string) {
  if (s === 'IN_PROGRESS') return 'chip-live';
  if (s === 'COMPLETED') return 'chip-done';
  if (s === 'WALKOVER') return 'chip-walkover';
  return 'chip-soon';
}

function timeShort(iso: string | null | undefined) {
  if (!iso) return '—';
  return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

const channelName = computed(() => `tournament.${props.tournament.id}`);

// Polling fallback for when Echo is unavailable (e.g. phone via ngrok —
// ws://localhost:8080 is unreachable, so connectEcho() returns null).
let pollTimer: ReturnType<typeof setInterval> | null = null;
let usePolling = false;

function startPolling() {
  if (pollTimer !== null) return;
  pollTimer = setInterval(() => {
    router.reload({
      only: ['live', 'matches', 'standings'],
      preserveScroll: true,
      preserveState: true,
    });
  }, 4000);
}

function stopPolling() {
  if (pollTimer !== null) {
    clearInterval(pollTimer);
    pollTimer = null;
  }
}

function onVisibilityChange() {
  if (!usePolling) return;
  if (document.hidden) stopPolling();
  else startPolling();
}

onMounted(() => {
  const echo = connectEcho();
  if (echo && typeof window !== 'undefined' && window.Echo) {
    window.Echo
      .channel(channelName.value)
      .listen('.match.updated', (e: { match: MatchDetail }) => {
        applyMatchUpdate(e.match);
      });
  } else {
    usePolling = true;
    startPolling();
    document.addEventListener('visibilitychange', onVisibilityChange);
  }
});

onUnmounted(() => {
  stopPolling();
  document.removeEventListener('visibilitychange', onVisibilityChange);
  if (typeof window !== 'undefined' && window.Echo) {
    window.Echo.leaveChannel(channelName.value);
  }
});
</script>

<template>
  <Head :title="tournament.name" />
  <div class="min-h-screen flex flex-col bg-slate-50">
    <!-- HERO -->
    <header class="gradient-hero text-white">
      <div class="max-w-6xl mx-auto px-4 sm:px-6 pt-5 pb-1 flex items-center justify-between">
        <Link :href="route('public.index')" class="text-slate-300 hover:text-white text-sm font-medium">
          ← Back to tournaments
        </Link>
      </div>
      <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 sm:py-12">
        <div class="flex flex-wrap items-center gap-3 mb-3">
          <span
            :class="
              tournament.status === 'IN_PROGRESS'
                ? 'chip bg-live-500/20 text-live-400 ring-live-500/30'
                : 'chip bg-white/10 text-white/80 ring-white/20'
            "
          >
            <span v-if="tournament.status === 'IN_PROGRESS'" class="live-dot mr-1.5"></span>
            {{ tournament.status }}
          </span>
          <span class="chip bg-white/10 text-white/80 ring-white/20">
            {{ tournament.format }}
          </span>
          <span v-if="liveCount > 0" class="chip bg-live-500/20 text-live-400 ring-live-500/30">
            {{ liveCount }} court{{ liveCount === 1 ? '' : 's' }} live
          </span>
        </div>
        <h1 class="text-display text-5xl sm:text-7xl tracking-tight leading-[0.95]">
          {{ tournament.name }}
        </h1>
        <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-slate-300 text-sm sm:text-base">
          <span v-if="tournament.venue">📍 {{ tournament.venue }}</span>
          <span v-if="tournament.startDate">
            🗓
            {{ new Date(tournament.startDate).toLocaleDateString() }}
            <span v-if="tournament.endDate">
              — {{ new Date(tournament.endDate).toLocaleDateString() }}
            </span>
          </span>
        </div>
      </div>
    </header>

    <main class="flex-1 max-w-6xl w-full mx-auto px-4 sm:px-6 py-8 space-y-10">
      <!-- Now playing -->
      <section v-if="liveCards.length > 0">
        <div class="flex items-baseline justify-between mb-4">
          <h2 class="text-display text-3xl sm:text-4xl text-slate-900 leading-none">
            Now playing
          </h2>
          <span class="text-sm text-slate-500">
            {{ liveCount }} live · {{ live.length }} total courts
          </span>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="c in liveCards"
            :key="c.court.id"
            class="rounded-2xl bg-white ring-1 ring-slate-200 p-5"
          >
            <div class="flex items-center justify-between mb-3">
              <span class="text-display text-2xl text-brand-700 leading-none">{{ c.court.name }}</span>
              <span v-if="c.current" class="chip-live">
                <span class="live-dot mr-1.5"></span>LIVE
              </span>
              <span v-else class="chip-soon">IDLE</span>
            </div>
            <template v-if="c.current">
              <div class="space-y-1.5">
                <div class="flex items-center justify-between gap-2">
                  <span :class="c.current.winnerId === c.current.teamA?.id ? 'font-semibold text-slate-900' : 'text-slate-700'">
                    {{ c.current.teamA?.displayName || 'TBD' }}
                  </span>
                  <span v-if="c.current.sets.length" class="font-mono text-sm text-slate-700">
                    {{ c.current.sets.map(s => s.teamAScore).join('·') }}
                  </span>
                </div>
                <div class="flex items-center justify-between gap-2">
                  <span :class="c.current.winnerId === c.current.teamB?.id ? 'font-semibold text-slate-900' : 'text-slate-700'">
                    {{ c.current.teamB?.displayName || 'TBD' }}
                  </span>
                  <span v-if="c.current.sets.length" class="font-mono text-sm text-slate-700">
                    {{ c.current.sets.map(s => s.teamBScore).join('·') }}
                  </span>
                </div>
              </div>
            </template>
            <div v-else-if="c.next" class="text-sm text-slate-500">
              Next: {{ c.next.teamA?.displayName }} vs {{ c.next.teamB?.displayName }}
            </div>
            <div v-else class="text-sm text-slate-400">No upcoming matches</div>
          </div>
        </div>
      </section>

      <!-- Coming up -->
      <section v-if="upcomingMatches.length > 0">
        <div class="flex items-baseline justify-between mb-4">
          <h2 class="text-display text-3xl sm:text-4xl text-slate-900 leading-none">
            Coming up
          </h2>
          <span class="text-sm text-slate-500">Next {{ upcomingMatches.length }} matches</span>
        </div>
        <div class="flex gap-3 overflow-x-auto pb-2 -mx-4 px-4 sm:mx-0 sm:px-0">
          <div
            v-for="m in upcomingMatches"
            :key="m.id"
            class="min-w-[240px] rounded-xl bg-white ring-1 ring-slate-200 p-4"
          >
            <div class="flex items-center justify-between mb-2">
              <span class="text-display text-lg text-brand-700 leading-none">
                {{ m.court?.name || 'TBD' }}
              </span>
              <span class="text-xs text-slate-400">{{ timeShort(m.scheduledAt) }}</span>
            </div>
            <div class="text-xs uppercase tracking-widest text-slate-400 mb-1">
              {{ m.categoryName || m.categoryType }}
            </div>
            <div class="text-sm text-slate-800">{{ m.teamA?.displayName || 'TBD' }}</div>
            <div class="text-sm text-slate-800">{{ m.teamB?.displayName || 'TBD' }}</div>
          </div>
        </div>
      </section>

      <!-- Sub-tabs for explorer -->
      <section>
        <nav class="border-b border-slate-200 mb-5 flex gap-1 sm:gap-2 overflow-x-auto">
          <button
            v-for="tname in (['schedule','bracket','standings'] as const)"
            :key="tname"
            :class="[
              'px-4 py-2.5 -mb-px border-b-2 text-sm font-semibold whitespace-nowrap transition',
              tab === tname
                ? 'border-brand-600 text-brand-700'
                : 'border-transparent text-slate-500 hover:text-slate-700',
            ]"
            @click="tab = tname"
          >
            {{ tname === 'schedule' ? 'Full schedule' : tname === 'bracket' ? 'Bracket' : 'Standings' }}
          </button>
        </nav>

        <div v-if="tournament.categories.length > 0" class="mb-5">
          <label class="text-xs uppercase tracking-widest text-slate-400 mr-2">Category</label>
          <select v-model="selectedCategoryId" class="input max-w-xs inline-block">
            <option v-for="c in tournament.categories" :key="c.id" :value="c.id">
              {{ c.name }}
            </option>
          </select>
        </div>

        <!-- SCHEDULE -->
        <div v-if="tab === 'schedule'">
          <div v-if="scheduleByDate.length === 0" class="card text-center text-slate-500">
            No matches yet
          </div>
          <div v-else class="space-y-6">
            <div v-for="grp in scheduleByDate" :key="grp.date">
              <h3 class="text-display text-2xl text-slate-700 mb-3">{{ grp.date }}</h3>
              <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <div
                  v-for="m in grp.items"
                  :key="m.id"
                  class="rounded-xl bg-white ring-1 ring-slate-200 p-4"
                >
                  <div class="flex items-center justify-between mb-2">
                    <span class="text-display text-xl text-brand-700 leading-none">
                      {{ m.court?.name || '—' }}
                    </span>
                    <span :class="statusChipClass(m.status)">
                      {{ m.status }}
                    </span>
                  </div>
                  <div class="text-xs uppercase tracking-widest text-slate-400">
                    {{ m.categoryName || m.categoryType }} · {{ m.stage }}
                  </div>
                  <div class="mt-1 flex items-baseline justify-between gap-2">
                    <span :class="m.winnerId === m.teamA?.id ? 'font-semibold text-slate-900' : 'text-slate-700'">
                      {{ m.teamA?.displayName || 'TBD' }}
                    </span>
                    <span v-if="m.sets.length" class="font-mono text-sm text-slate-700">
                      {{ m.sets.map(s => s.teamAScore).join('·') }}
                    </span>
                  </div>
                  <div class="flex items-baseline justify-between gap-2">
                    <span :class="m.winnerId === m.teamB?.id ? 'font-semibold text-slate-900' : 'text-slate-700'">
                      {{ m.teamB?.displayName || 'TBD' }}
                    </span>
                    <span v-if="m.sets.length" class="font-mono text-sm text-slate-700">
                      {{ m.sets.map(s => s.teamBScore).join('·') }}
                    </span>
                  </div>
                  <div v-if="m.scheduledAt" class="text-xs text-slate-400 mt-2">
                    ⏱ {{ timeShort(m.scheduledAt) }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- BRACKET -->
        <div v-if="tab === 'bracket'">
          <!-- Empty state -->
          <div
            v-if="!hasBracket"
            class="gradient-hero text-white rounded-2xl p-10 text-center"
          >
            <div class="text-5xl mb-3">🏆</div>
            <p class="text-display text-3xl tracking-tight">Bracket appears after the draw</p>
            <p class="text-white/60 text-sm mt-1">
              The knockout tree will show once group stage seeds are resolved.
            </p>
          </div>

          <!-- Bracket tree -->
          <div v-else class="gradient-hero text-white rounded-2xl p-4 sm:p-8 overflow-x-auto">
            <!-- ===== Semifinals + Final + Champion (the main tree) ===== -->
            <div class="lg:flex lg:items-stretch lg:gap-0">
              <!-- Column: Semifinals -->
              <div class="lg:flex-1 lg:min-w-[230px]">
                <p class="text-display text-2xl text-white/70 mb-3 lg:hidden">Semifinals</p>
                <div class="hidden lg:block text-display text-xl text-white/50 mb-4 tracking-wide">SEMIFINALS</div>
                <div class="flex flex-col gap-4 lg:gap-16 lg:justify-around lg:h-full">
                  <!-- SF1 -->
                  <div class="bracket-node sf-top relative">
                    <div v-if="sf1" class="match-card">
                      <div class="match-card-head">
                        <span class="text-display text-sm text-white/50 tracking-wider">SEMIFINAL 1</span>
                        <span :class="['bracket-chip', bracketStatusChip(sf1.status).toLowerCase()]">
                          <span v-if="sf1.status === 'IN_PROGRESS'" class="live-dot mr-1"></span>
                          {{ bracketStatusChip(sf1.status) }}
                        </span>
                      </div>
                      <div :class="['team-slot', sf1.winnerId && sf1.winnerId === sf1.teamA?.id ? 'is-winner' : sf1.winnerId ? 'is-loser' : '']">
                        <span class="team-name">{{ sf1.teamA?.displayName || sourceLabel(sf1.teamASource) }}</span>
                        <span class="team-score" v-if="sf1.sets.length">{{ sf1.sets.map(s => s.teamAScore).join(' ') }}</span>
                      </div>
                      <div :class="['team-slot', sf1.winnerId && sf1.winnerId === sf1.teamB?.id ? 'is-winner' : sf1.winnerId ? 'is-loser' : '']">
                        <span class="team-name">{{ sf1.teamB?.displayName || sourceLabel(sf1.teamBSource) }}</span>
                        <span class="team-score" v-if="sf1.sets.length">{{ sf1.sets.map(s => s.teamBScore).join(' ') }}</span>
                      </div>
                      <div class="match-card-foot">⏱ {{ timeShort(sf1.scheduledAt) }}</div>
                    </div>
                  </div>
                  <!-- SF2 -->
                  <div class="bracket-node sf-bottom relative">
                    <div v-if="sf2" class="match-card">
                      <div class="match-card-head">
                        <span class="text-display text-sm text-white/50 tracking-wider">SEMIFINAL 2</span>
                        <span :class="['bracket-chip', bracketStatusChip(sf2.status).toLowerCase()]">
                          <span v-if="sf2.status === 'IN_PROGRESS'" class="live-dot mr-1"></span>
                          {{ bracketStatusChip(sf2.status) }}
                        </span>
                      </div>
                      <div :class="['team-slot', sf2.winnerId && sf2.winnerId === sf2.teamA?.id ? 'is-winner' : sf2.winnerId ? 'is-loser' : '']">
                        <span class="team-name">{{ sf2.teamA?.displayName || sourceLabel(sf2.teamASource) }}</span>
                        <span class="team-score" v-if="sf2.sets.length">{{ sf2.sets.map(s => s.teamAScore).join(' ') }}</span>
                      </div>
                      <div :class="['team-slot', sf2.winnerId && sf2.winnerId === sf2.teamB?.id ? 'is-winner' : sf2.winnerId ? 'is-loser' : '']">
                        <span class="team-name">{{ sf2.teamB?.displayName || sourceLabel(sf2.teamBSource) }}</span>
                        <span class="team-score" v-if="sf2.sets.length">{{ sf2.sets.map(s => s.teamBScore).join(' ') }}</span>
                      </div>
                      <div class="match-card-foot">⏱ {{ timeShort(sf2.scheduledAt) }}</div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Connector: semis -> final (desktop only) -->
              <div class="hidden lg:flex lg:w-10 lg:flex-col lg:justify-center">
                <div class="bracket-connector"></div>
              </div>

              <!-- Column: Final -->
              <div class="lg:flex-1 lg:min-w-[230px] lg:flex lg:flex-col lg:justify-center">
                <p class="text-display text-2xl text-white/70 mb-3 mt-6 lg:mt-0 lg:hidden">Final</p>
                <div class="hidden lg:block text-display text-xl text-white/50 mb-4 tracking-wide">FINAL</div>
                <div v-if="finalMatch" class="match-card match-card-final relative">
                  <div class="match-card-head">
                    <span class="text-display text-sm text-accent-400 tracking-wider">🏆 FINAL</span>
                    <span :class="['bracket-chip', bracketStatusChip(finalMatch.status).toLowerCase()]">
                      <span v-if="finalMatch.status === 'IN_PROGRESS'" class="live-dot mr-1"></span>
                      {{ bracketStatusChip(finalMatch.status) }}
                    </span>
                  </div>
                  <div :class="['team-slot', finalMatch.winnerId && finalMatch.winnerId === finalMatch.teamA?.id ? 'is-winner' : finalMatch.winnerId ? 'is-loser' : '']">
                    <span class="team-name">{{ finalMatch.teamA?.displayName || sourceLabel(finalMatch.teamASource) }}</span>
                    <span class="team-score" v-if="finalMatch.sets.length">{{ finalMatch.sets.map(s => s.teamAScore).join(' ') }}</span>
                  </div>
                  <div :class="['team-slot', finalMatch.winnerId && finalMatch.winnerId === finalMatch.teamB?.id ? 'is-winner' : finalMatch.winnerId ? 'is-loser' : '']">
                    <span class="team-name">{{ finalMatch.teamB?.displayName || sourceLabel(finalMatch.teamBSource) }}</span>
                    <span class="team-score" v-if="finalMatch.sets.length">{{ finalMatch.sets.map(s => s.teamBScore).join(' ') }}</span>
                  </div>
                  <div class="match-card-foot">⏱ {{ timeShort(finalMatch.scheduledAt) }}</div>
                </div>
              </div>

              <!-- Connector: final -> champion (desktop only) -->
              <div class="hidden lg:flex lg:w-10 lg:flex-col lg:justify-center">
                <div class="bracket-connector-flat"></div>
              </div>

              <!-- Column: Champion -->
              <div class="lg:flex-1 lg:min-w-[180px] lg:flex lg:flex-col lg:justify-center">
                <div class="champion-card mt-6 lg:mt-0">
                  <div class="text-4xl mb-1">🏆</div>
                  <div class="text-display text-xs tracking-[0.2em] text-accent-400">CHAMPION</div>
                  <div
                    :class="[
                      'text-display text-2xl mt-1 leading-tight',
                      championName ? 'text-white' : 'text-white/40',
                    ]"
                  >
                    {{ championName || 'TBD' }}
                  </div>
                </div>
              </div>
            </div>

            <!-- ===== Bronze final (separate row) ===== -->
            <div v-if="bronzeMatch" class="mt-8 pt-6 border-t border-white/10">
              <div class="lg:max-w-sm">
                <div class="match-card match-card-bronze relative">
                  <div class="match-card-head">
                    <span class="bronze-chip">BRONZE-FINAL</span>
                    <span :class="['bracket-chip', bracketStatusChip(bronzeMatch.status).toLowerCase()]">
                      <span v-if="bronzeMatch.status === 'IN_PROGRESS'" class="live-dot mr-1"></span>
                      {{ bracketStatusChip(bronzeMatch.status) }}
                    </span>
                  </div>
                  <div :class="['team-slot', bronzeMatch.winnerId && bronzeMatch.winnerId === bronzeMatch.teamA?.id ? 'is-winner' : bronzeMatch.winnerId ? 'is-loser' : '']">
                    <span class="team-name">{{ bronzeMatch.teamA?.displayName || sourceLabel(bronzeMatch.teamASource) }}</span>
                    <span class="team-score" v-if="bronzeMatch.sets.length">{{ bronzeMatch.sets.map(s => s.teamAScore).join(' ') }}</span>
                  </div>
                  <div :class="['team-slot', bronzeMatch.winnerId && bronzeMatch.winnerId === bronzeMatch.teamB?.id ? 'is-winner' : bronzeMatch.winnerId ? 'is-loser' : '']">
                    <span class="team-name">{{ bronzeMatch.teamB?.displayName || sourceLabel(bronzeMatch.teamBSource) }}</span>
                    <span class="team-score" v-if="bronzeMatch.sets.length">{{ bronzeMatch.sets.map(s => s.teamBScore).join(' ') }}</span>
                  </div>
                  <div class="match-card-foot">⏱ {{ timeShort(bronzeMatch.scheduledAt) }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- STANDINGS -->
        <div v-if="tab === 'standings'" class="space-y-4">
          <div v-if="standingsForCategory.length === 0" class="card text-center text-slate-500">
            No standings yet
          </div>
          <div
            v-for="g in standingsForCategory"
            :key="g.groupId"
            class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden"
          >
            <div class="px-5 py-3 border-b border-slate-100 flex items-center gap-2">
              <span class="text-display text-xl text-brand-700 leading-none">{{ g.name }}</span>
            </div>
            <table class="min-w-full text-sm">
              <thead class="bg-slate-50">
                <tr class="text-xs uppercase tracking-wider text-slate-400">
                  <th class="text-left py-2 px-4">#</th>
                  <th class="text-left py-2 px-4">Team</th>
                  <th class="text-right py-2 px-3">P</th>
                  <th class="text-right py-2 px-3">W</th>
                  <th class="text-right py-2 px-3">L</th>
                  <th class="text-right py-2 px-3">Sets</th>
                  <th class="text-right py-2 px-4">Diff</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(r, i) in g.rows" :key="r.teamId" class="border-t border-slate-100">
                  <td class="py-2 px-4 text-slate-400 font-semibold">{{ i + 1 }}</td>
                  <td class="py-2 px-4 font-medium text-slate-800">{{ r.teamName }}</td>
                  <td class="py-2 px-3 text-right text-slate-600">{{ r.played }}</td>
                  <td class="py-2 px-3 text-right text-slate-600">{{ r.won }}</td>
                  <td class="py-2 px-3 text-right text-slate-600">{{ r.lost }}</td>
                  <td class="py-2 px-3 text-right text-slate-600">{{ r.setsFor }}-{{ r.setsAgainst }}</td>
                  <td
                    class="py-2 px-4 text-right font-mono"
                    :class="
                      r.pointsFor - r.pointsAgainst > 0
                        ? 'text-emerald-600'
                        : r.pointsFor - r.pointsAgainst < 0
                          ? 'text-rose-600'
                          : 'text-slate-500'
                    "
                  >
                    {{ r.pointsFor - r.pointsAgainst > 0 ? '+' : ''
                    }}{{ r.pointsFor - r.pointsAgainst }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </main>

    <footer class="bg-white border-t border-slate-200 py-6 text-center text-xs text-slate-400">
      Tournament Service · BWF rules
    </footer>
  </div>
</template>

<style scoped>
/* Bracket connectors — pure CSS, only drawn on the wide (lg) tree layout.
   The semis->final connector is an C-shaped bracket: a vertical spine with
   two horizontal arms reaching toward the (taller) final card. */
.bracket-connector {
  position: relative;
  width: 100%;
  height: 60%;
  margin: auto 0;
  border-right: 2px solid rgba(99, 102, 241, 0.5); /* brand-500 spine */
  border-top: 2px solid rgba(99, 102, 241, 0.5);
  border-bottom: 2px solid rgba(99, 102, 241, 0.5);
  border-top-right-radius: 8px;
  border-bottom-right-radius: 8px;
}
/* stub from the spine's midpoint into the final card */
.bracket-connector::after {
  content: '';
  position: absolute;
  top: 50%;
  right: -10px;
  width: 10px;
  border-top: 2px solid rgba(99, 102, 241, 0.5);
}
/* flat connector: final -> champion */
.bracket-connector-flat {
  position: relative;
  width: 100%;
  margin: auto 0;
  border-top: 2px solid rgba(250, 204, 21, 0.5); /* accent-400 */
}
</style>
