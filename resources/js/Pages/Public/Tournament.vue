<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { connectEcho, type ReverbConfig } from '../../bootstrap';
import PublicLayout, { type PublicTab } from '../../Layouts/PublicLayout.vue';
import MatchCard, { type MatchCardMatch } from '../../Components/Public/MatchCard.vue';
import BracketTree, { type BracketMatch } from '../../Components/Public/BracketTree.vue';
import StandingsTable from '../../Components/Public/StandingsTable.vue';

interface Team {
  id: string;
  displayName: string;
  seed?: number | null;
  players?: { fullName: string; club?: string | null }[] | null;
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
  stageLabel?: string | null;
  scheduledAt?: string | null;
  startedAt?: string | null;
  completedAt?: string | null;
  categoryId?: string | null;
  categoryName?: string | null;
  categoryType?: string | null;
  categoryCode?: string | null;
  teamA?: Team | null;
  teamB?: Team | null;
  court?: Court | null;
  courtName?: string | null;
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
  points_to_win?: number;
  sets_to_win?: number;
  deuce_cap?: number;
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
interface PlayerEntry {
  fullName: string;
  club?: string | null;
}
interface PlayersTeam {
  id: string;
  seed?: number | null;
  displayName: string;
  players: PlayerEntry[];
}
interface PlayersCategory {
  categoryId: string;
  categoryName: string;
  categoryCode: string;
  teams: PlayersTeam[];
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
  players: PlayersCategory[];
}>();

const tournament = ref<TournamentDetail>(props.tournament);
const live = ref<LiveCourtCard[]>([...props.live]);
const matches = ref<MatchDetail[]>([...props.matches]);
const standings = ref<GroupStanding[]>([...props.standings]);
const players = ref<PlayersCategory[]>([...props.players]);

// Partial reloads (polling fallback) update props but NOT the local refs
// copied above — sync them so polled data actually refreshes the UI.
watch(() => props.live, (v) => { live.value = [...v]; });
watch(() => props.matches, (v) => { matches.value = [...v]; });
watch(() => props.standings, (v) => { standings.value = [...v]; });
watch(() => props.players, (v) => { players.value = [...v]; });

// ---- tabbed layout ----------------------------------------------------------
const TABS: PublicTab[] = [
  { key: 'overview', label: 'Overview' },
  { key: 'players', label: 'Players' },
  { key: 'draw', label: 'Draw' },
  { key: 'matches', label: 'Matches' },
];
const tab = ref<'overview' | 'players' | 'draw' | 'matches'>('overview');

// Per-tab category selectors (independent so switching tabs feels stable).
const drawCategoryId = ref<string | null>(
  props.tournament.categories.length > 0 ? props.tournament.categories[0].id : null,
);
const matchesCategoryId = ref<string | null>(null); // null = all categories
const playersCategoryId = ref<string | null>(
  props.players.length > 0 ? props.players[0].categoryId : null,
);

// ---- realtime application ---------------------------------------------------
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

// ---- shared mapping: MatchDetail -> MatchCardMatch --------------------------
// Map the backend payload onto the foundation MatchCard contract:
//  stageLabel -> roundLabel, courtName is the bare name (card prefixes "Court ").
function toCardMatch(m: MatchDetail): MatchCardMatch {
  return {
    id: m.id,
    status: m.status,
    teamA: m.teamA ?? null,
    teamB: m.teamB ?? null,
    sets: m.sets ?? [],
    winnerId: m.winnerId ?? null,
    categoryCode: m.categoryCode ?? m.categoryName ?? null,
    roundLabel: m.stageLabel ?? null,
    courtName: m.courtName ?? m.court?.name ?? null,
    scheduledAt: m.scheduledAt ?? null,
    startedAt: m.startedAt ?? null,
    completedAt: m.completedAt ?? null,
  };
}

// ---- OVERVIEW data ----------------------------------------------------------
const isLiveTournament = computed(() => tournament.value.status === 'IN_PROGRESS');
const liveCount = computed(() => live.value.filter((c) => c.current).length);

// Courts that currently have a live match.
const liveCourts = computed(() => live.value.filter((c) => c.current));

const upcomingMatches = computed(() =>
  matches.value
    .filter((m) => m.status === 'SCHEDULED' && m.teamA && m.teamB)
    .sort(byScheduled)
    .slice(0, 8),
);

// Results are entered after a match finishes (no live scoring), so the
// Overview surfaces the most recently completed matches instead of a "live" feed.
const recentResults = computed(() =>
  matches.value
    .filter((m) => (m.status === 'COMPLETED' || m.status === 'WALKOVER') && m.teamA && m.teamB)
    .sort((a, b) => byScheduled(b, a))
    .slice(0, 6),
);

function byScheduled(a: MatchDetail, b: MatchDetail): number {
  const ta = a.scheduledAt ? new Date(a.scheduledAt).getTime() : Number.MAX_SAFE_INTEGER;
  const tb = b.scheduledAt ? new Date(b.scheduledAt).getTime() : Number.MAX_SAFE_INTEGER;
  return ta - tb;
}

function fmtDate(iso: string | null | undefined): string {
  if (!iso) return '—';
  return new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Asia/Yangon',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(new Date(iso));
}
const dateRange = computed(() => {
  const s = tournament.value.startDate;
  const e = tournament.value.endDate;
  if (!s) return '—';
  if (!e) return fmtDate(s);
  return `${fmtDate(s)} — ${fmtDate(e)}`;
});
const scoringLine = computed(() => {
  const t = tournament.value;
  const pts = t.points_to_win ?? 21;
  const sets = t.sets_to_win ?? 2;
  const cap = t.deuce_cap ?? 30;
  return `Best of ${sets * 2 - 1} · ${pts} pts · cap ${cap}`;
});

// ---- PLAYERS data -----------------------------------------------------------
const selectedPlayersCategory = computed<PlayersCategory | null>(() => {
  if (!playersCategoryId.value) return players.value[0] ?? null;
  return players.value.find((p) => p.categoryId === playersCategoryId.value) ?? null;
});

// ---- DRAW data --------------------------------------------------------------
const CATEGORY_CODE: Record<string, string> = {
  MENS_SINGLES: 'MS',
  WOMENS_SINGLES: 'WS',
  MENS_DOUBLES: 'MD',
  WOMENS_DOUBLES: 'WD',
  MIXED_DOUBLES: 'XD',
};
const CATEGORY_TYPE_LABEL: Record<string, string> = {
  MENS_SINGLES: 'Singles',
  WOMENS_SINGLES: 'Singles',
  MENS_DOUBLES: 'Doubles',
  WOMENS_DOUBLES: 'Doubles',
  MIXED_DOUBLES: 'Mixed',
};
function codeFor(cat: Category): string {
  return CATEGORY_CODE[cat.type] ?? cat.type?.slice(0, 2)?.toUpperCase() ?? '—';
}
function typeFor(cat: Category): string {
  return CATEGORY_TYPE_LABEL[cat.type] ?? '—';
}
function teamCountFor(categoryId: string): number {
  const fromPlayers = players.value.find((p) => p.categoryId === categoryId);
  if (fromPlayers) return fromPlayers.teams.length;
  // fallback: distinct teams across that category's matches
  const ids = new Set<string>();
  for (const m of matches.value) {
    if (m.categoryId !== categoryId) continue;
    if (m.teamA?.id) ids.add(m.teamA.id);
    if (m.teamB?.id) ids.add(m.teamB.id);
  }
  return ids.size;
}

function viewBracket(categoryId: string) {
  drawCategoryId.value = categoryId;
}

const selectedDrawCategory = computed<Category | null>(() =>
  tournament.value.categories.find((c) => c.id === drawCategoryId.value) ?? null,
);

const standingsForDraw = computed<GroupStanding[]>(() => {
  if (!drawCategoryId.value) return [];
  return standings.value.filter((g) => g.categoryId === drawCategoryId.value);
});

// Knockout subset for the selected draw category -> BracketTree contract.
const KNOCKOUT_STAGES = new Set(['SEMIFINAL', 'FINAL', 'THIRD_PLACE']);
const bracketMatches = computed<BracketMatch[]>(() =>
  matches.value
    .filter(
      (m) =>
        m.categoryId === drawCategoryId.value &&
        m.stage != null &&
        KNOCKOUT_STAGES.has(m.stage),
    )
    .map((m) => ({
      id: m.id,
      status: m.status,
      stage: m.stage,
      bracketSlot: m.bracketSlot,
      teamA: m.teamA ?? null,
      teamB: m.teamB ?? null,
      teamASource: m.teamASource ?? null,
      teamBSource: m.teamBSource ?? null,
      winnerId: m.winnerId ?? null,
      sets: m.sets ?? [],
    })),
);
const hasBracket = computed(() => bracketMatches.value.length > 0);

// ---- MATCHES data (court-grouped schedule) ----------------------------------
const matchesFiltered = computed<MatchDetail[]>(() => {
  if (!matchesCategoryId.value) return matches.value;
  return matches.value.filter((m) => m.categoryId === matchesCategoryId.value);
});

// Group by court; within a court order live -> scheduled -> completed, then by
// time. Each court becomes a section of MatchCards (Match 1..n).
const matchesByCourt = computed(() => {
  const groups = new Map<string, { court: Court | null; items: MatchDetail[] }>();
  const noCourtKey = '__nocourt__';

  for (const m of matchesFiltered.value) {
    const key = m.court?.id ?? noCourtKey;
    if (!groups.has(key)) {
      groups.set(key, { court: m.court ?? null, items: [] });
    }
    groups.get(key)!.items.push(m);
  }

  const order = (m: MatchDetail) =>
    m.status === 'IN_PROGRESS' ? 0 : m.status === 'SCHEDULED' ? 1 : 2;

  const sections = Array.from(groups.values()).map((g) => ({
    court: g.court,
    items: [...g.items].sort((a, b) => {
      const d = order(a) - order(b);
      return d !== 0 ? d : byScheduled(a, b);
    }),
  }));

  // Courts first (numeric name order), unscheduled bucket last.
  sections.sort((a, b) => {
    if (!a.court) return 1;
    if (!b.court) return -1;
    return a.court.name.localeCompare(b.court.name, undefined, { numeric: true });
  });
  return sections;
});

const hasAnyMatch = computed(() => matchesFiltered.value.length > 0);

// Flat list of all (filtered) matches, ordered by time then court — the
// "all matches" list view.
const matchesSorted = computed<MatchDetail[]>(() =>
  [...matchesFiltered.value].sort((a, b) => {
    const d = byScheduled(a, b);
    if (d !== 0) return d;
    return (a.court?.name ?? '').localeCompare(b.court?.name ?? '', undefined, { numeric: true });
  }),
);

function fmtTime(iso: string | null | undefined): string {
  if (!iso) return 'TBD';
  return new Intl.DateTimeFormat('en-US', {
    timeZone: 'Asia/Yangon', hour: 'numeric', minute: '2-digit', hour12: true,
  }).format(new Date(iso));
}

function scoreLine(m: MatchDetail): string {
  if (!m.sets || m.sets.length === 0) return '';
  return m.sets.map((s) => `${s.teamAScore}-${s.teamBScore}`).join('  ');
}

function isDone(m: MatchDetail): boolean {
  return m.status === 'COMPLETED' || m.status === 'WALKOVER';
}

function winClass(m: MatchDetail, teamId: string | null | undefined): string {
  return m.winnerId && teamId && m.winnerId === teamId
    ? 'font-semibold text-[var(--color-bwf-text)]'
    : 'text-[var(--color-bwf-text-2)]';
}

// State label for a court's match list: live = "Live", done = "Final", the
// first upcoming = "Starts" and any later upcoming = "Followed By".
function stateLabelFor(m: MatchDetail, indexInCourt: number, items: MatchDetail[]): string {
  if (m.status === 'IN_PROGRESS') return 'Live';
  if (m.status === 'COMPLETED' || m.status === 'WALKOVER') return 'Final';
  const firstScheduledIdx = items.findIndex((x) => x.status === 'SCHEDULED');
  return indexInCourt === firstScheduledIdx ? 'Starts' : 'Followed By';
}

// ---- realtime: Echo + polling fallback --------------------------------------
const channelName = computed(() => `tournament.${props.tournament.id}`);

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
  const reverb = usePage().props.reverb as ReverbConfig | null;
  const echo = connectEcho(reverb);
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
  <PublicLayout :tabs="TABS" v-model:tab="tab">
    <!-- ============================ HERO ============================ -->
    <template #hero>
      <Link
        :href="route('public.index')"
        class="bwf-label mb-3 inline-flex items-center gap-1 text-[var(--color-bwf-text-2)] transition hover:text-[var(--color-bwf-text)]"
      >
        &larr; All tournaments
      </Link>
      <div class="flex flex-wrap items-center gap-2">
        <span v-if="isLiveTournament" class="bwf-chip-live">
          <span class="bwf-dot bwf-dot-live" /> Live
        </span>
        <span
          v-else
          class="bwf-label rounded-full bg-[var(--color-bwf-raised)] px-2.5 py-1 ring-1 ring-white/5"
        >{{ tournament.status }}</span>
        <span
          v-if="liveCount > 0"
          class="bwf-label rounded-full bg-[var(--color-bwf-raised)] px-2.5 py-1 ring-1 ring-white/5"
        >{{ liveCount }} court{{ liveCount === 1 ? '' : 's' }} live</span>
      </div>
      <h1 class="mt-2 text-2xl font-bold tracking-tight text-[var(--color-bwf-text)] sm:text-4xl">
        {{ tournament.name }}
      </h1>
      <div
        class="mt-2 flex flex-wrap items-center gap-x-5 gap-y-1 text-sm text-[var(--color-bwf-text-2)]"
      >
        <span v-if="tournament.venue">{{ tournament.venue }}</span>
        <span>{{ dateRange }}</span>
        <span class="text-[var(--color-bwf-muted)]">{{ tournament.format }}</span>
      </div>
    </template>

    <!-- ============================ OVERVIEW ============================ -->
    <section v-if="tab === 'overview'" class="space-y-8">
      <!-- info grid -->
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="bwf-surface p-3">
          <p class="bwf-label">Venue</p>
          <p class="mt-1 text-sm font-medium text-[var(--color-bwf-text)]">
            {{ tournament.venue || '—' }}
          </p>
        </div>
        <div class="bwf-surface p-3">
          <p class="bwf-label">Dates</p>
          <p class="mt-1 text-sm font-medium text-[var(--color-bwf-text)]">{{ dateRange }}</p>
        </div>
        <div class="bwf-surface p-3">
          <p class="bwf-label">Format</p>
          <p class="mt-1 text-sm font-medium text-[var(--color-bwf-text)]">
            {{ tournament.format }}
          </p>
        </div>
        <div class="bwf-surface p-3">
          <p class="bwf-label">Scoring</p>
          <p class="mt-1 font-mono text-xs text-[var(--color-bwf-text)]">{{ scoringLine }}</p>
        </div>
      </div>

      <!-- Upcoming -->
      <div v-if="upcomingMatches.length > 0">
        <h2 class="bwf-label mb-3">Upcoming</h2>
        <div class="grid gap-4 lg:grid-cols-2">
          <MatchCard
            v-for="m in upcomingMatches"
            :key="m.id"
            :match="toCardMatch(m)"
          />
        </div>
      </div>

      <!-- Latest results -->
      <div v-if="recentResults.length > 0">
        <h2 class="bwf-label mb-3">Latest results</h2>
        <div class="grid gap-4 lg:grid-cols-2">
          <MatchCard
            v-for="m in recentResults"
            :key="m.id"
            :match="toCardMatch(m)"
          />
        </div>
      </div>

      <div
        v-if="upcomingMatches.length === 0 && recentResults.length === 0"
        class="bwf-surface px-4 py-10 text-center text-sm text-[var(--color-bwf-muted)]"
      >
        No matches scheduled yet.
      </div>
    </section>

    <!-- ============================ PLAYERS ============================ -->
    <section v-else-if="tab === 'players'" class="space-y-5">
      <div
        v-if="players.length === 0"
        class="bwf-surface px-4 py-10 text-center text-sm text-[var(--color-bwf-muted)]"
      >
        No players entered yet.
      </div>
      <template v-else>
        <!-- category selector (chips) -->
        <div class="flex flex-wrap gap-2">
          <button
            v-for="p in players"
            :key="p.categoryId"
            type="button"
            :class="
              (playersCategoryId ?? players[0].categoryId) === p.categoryId
                ? 'bwf-tab-active'
                : 'bwf-tab'
            "
            @click="playersCategoryId = p.categoryId"
          >
            <span class="bwf-draw-code mr-1.5">{{ p.categoryCode }}</span>{{ p.categoryName }}
          </button>
        </div>

        <div v-if="selectedPlayersCategory" class="bwf-surface overflow-hidden">
          <div class="flex items-center justify-between border-b bwf-hairline px-4 py-2.5">
            <span class="bwf-label text-[var(--color-bwf-text-2)]">
              <span class="bwf-draw-code mr-1.5">{{ selectedPlayersCategory.categoryCode }}</span>
              {{ selectedPlayersCategory.categoryName }}
            </span>
            <span class="bwf-label">{{ selectedPlayersCategory.teams.length }} Teams</span>
          </div>

          <ul
            v-if="selectedPlayersCategory.teams.length"
            class="divide-y divide-[var(--color-bwf-hairline)]"
          >
            <li
              v-for="(t, i) in selectedPlayersCategory.teams"
              :key="t.id"
              class="flex items-start gap-3 px-4 py-3"
            >
              <span class="mt-0.5 w-6 shrink-0 text-right font-mono text-xs text-[var(--color-bwf-muted)]">
                {{ i + 1 }}
              </span>
              <div class="min-w-0 flex-1">
                <template v-if="t.players.length">
                  <p
                    v-for="(pl, pi) in t.players"
                    :key="pi"
                    class="truncate text-sm font-medium leading-tight text-[var(--color-bwf-text)]"
                  >
                    {{ pl.fullName }}
                    <span
                      v-if="pi === t.players.length - 1 && t.seed"
                      class="bwf-seed"
                    >({{ t.seed }})</span>
                  </p>
                  <p
                    v-if="t.players.some((x) => x.club)"
                    class="bwf-label mt-0.5 normal-case tracking-normal"
                  >
                    {{ [...new Set(t.players.map((x) => x.club).filter(Boolean))].join(' / ') }}
                  </p>
                </template>
                <p v-else class="truncate text-sm font-medium text-[var(--color-bwf-text)]">
                  {{ t.displayName }}
                  <span v-if="t.seed" class="bwf-seed">({{ t.seed }})</span>
                </p>
              </div>
            </li>
          </ul>
          <div v-else class="px-4 py-8 text-center text-sm text-[var(--color-bwf-muted)]">
            No teams in this category.
          </div>
        </div>
      </template>
    </section>

    <!-- ============================ DRAW ============================ -->
    <section v-else-if="tab === 'draw'" class="space-y-6">
      <div
        v-if="tournament.categories.length === 0"
        class="bwf-surface px-4 py-10 text-center text-sm text-[var(--color-bwf-muted)]"
      >
        No draws available yet.
      </div>
      <template v-else>
        <!-- draw list table (reference image 1 body) -->
        <div class="bwf-surface overflow-hidden">
          <div class="border-b bwf-hairline px-4 py-2.5">
            <span class="bwf-label text-[var(--color-bwf-text-2)]">Draws</span>
          </div>
          <table class="w-full text-sm">
            <thead>
              <tr class="bwf-label border-b bwf-hairline">
                <th class="w-10 py-2 pl-4 text-left font-semibold">No.</th>
                <th class="py-2 text-left font-semibold">Draw</th>
                <th class="w-16 py-2 text-center font-semibold">Size</th>
                <th class="w-24 py-2 text-left font-semibold">Type</th>
                <th class="w-32 py-2 pr-4 text-right font-semibold"></th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(cat, i) in tournament.categories"
                :key="cat.id"
                class="border-b bwf-hairline last:border-0"
                :class="cat.id === drawCategoryId ? 'bg-[var(--color-bwf-raised)]/60' : ''"
              >
                <td class="py-2.5 pl-4 font-mono text-xs text-[var(--color-bwf-muted)]">{{ i + 1 }}</td>
                <td class="py-2.5">
                  <span class="bwf-draw-code mr-2">{{ codeFor(cat) }}</span>
                  <span class="text-[var(--color-bwf-text-2)]">{{ cat.name }}</span>
                </td>
                <td class="py-2.5 text-center font-mono text-[var(--color-bwf-text-2)]">
                  {{ teamCountFor(cat.id) }}
                </td>
                <td class="py-2.5 text-[var(--color-bwf-text-2)]">{{ typeFor(cat) }}</td>
                <td class="py-2.5 pr-4 text-right">
                  <Link
                    :href="route('public.tournament.bracket', { tournament: tournament.id, category: cat.id })"
                    class="bwf-btn"
                  >
                    View Bracket
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- selected category detail: group standings + knockout bracket -->
        <div v-if="selectedDrawCategory" class="space-y-5">
          <h2 class="bwf-label flex items-center gap-2">
            <span class="bwf-draw-code">{{ codeFor(selectedDrawCategory) }}</span>
            {{ selectedDrawCategory.name }}
          </h2>

          <!-- group standings -->
          <div v-if="standingsForDraw.length" class="grid gap-4 lg:grid-cols-2">
            <StandingsTable
              v-for="g in standingsForDraw"
              :key="g.groupId"
              :name="g.name"
              :rows="g.rows"
              show-sets
              :qualify-count="2"
            />
          </div>

          <!-- knockout bracket -->
          <div>
            <h3 class="bwf-label mb-2 text-[var(--color-bwf-text-2)]">Knockout</h3>
            <BracketTree v-if="hasBracket" :matches="bracketMatches" />
            <div
              v-else
              class="bwf-surface px-4 py-8 text-center text-sm text-[var(--color-bwf-muted)]"
            >
              Knockout bracket appears once group seeds are resolved.
            </div>
          </div>
        </div>
      </template>
    </section>

    <!-- ============================ MATCHES ============================ -->
    <section v-else-if="tab === 'matches'" class="space-y-6">
      <!-- category filter (chips: All + each category) -->
      <div v-if="tournament.categories.length > 0" class="flex flex-wrap gap-2">
        <button
          type="button"
          :class="matchesCategoryId === null ? 'bwf-tab-active' : 'bwf-tab'"
          @click="matchesCategoryId = null"
        >
          All
        </button>
        <button
          v-for="cat in tournament.categories"
          :key="cat.id"
          type="button"
          :class="matchesCategoryId === cat.id ? 'bwf-tab-active' : 'bwf-tab'"
          @click="matchesCategoryId = cat.id"
        >
          <span class="bwf-draw-code mr-1.5">{{ codeFor(cat) }}</span>{{ cat.name }}
        </button>
      </div>

      <div
        v-if="!hasAnyMatch"
        class="bwf-surface px-4 py-10 text-center text-sm text-[var(--color-bwf-muted)]"
      >
        No matches scheduled yet.
      </div>

      <!-- all matches: list view -->
      <div v-if="hasAnyMatch" class="bwf-surface overflow-hidden">
        <!-- column header (desktop) -->
        <div
          class="hidden grid-cols-[80px_1fr_140px_88px] gap-3 border-b bwf-hairline px-4 py-2 sm:grid"
        >
          <span class="bwf-label">Time</span>
          <span class="bwf-label">Match</span>
          <span class="bwf-label text-right">Score</span>
          <span class="bwf-label text-right">Court</span>
        </div>

        <div
          v-for="m in matchesSorted"
          :key="m.id"
          class="border-b bwf-hairline px-4 py-3 last:border-0 sm:grid sm:grid-cols-[80px_1fr_140px_88px] sm:items-center sm:gap-3"
        >
          <!-- time + state -->
          <div class="flex items-center justify-between sm:block">
            <div class="font-mono text-sm text-[var(--color-bwf-text)]">
              {{ fmtTime(m.scheduledAt) }}
            </div>
            <div
              class="bwf-label"
              :class="isDone(m) ? 'text-[var(--color-bwf-red)]' : 'text-[var(--color-bwf-muted)]'"
            >
              {{ isDone(m) ? 'Final' : 'Scheduled' }}
            </div>
          </div>

          <!-- match: code/round + the two sides -->
          <div class="mt-2 sm:mt-0">
            <div class="mb-1 flex items-center gap-2">
              <span class="bwf-draw-code">{{ m.categoryCode ?? m.categoryName }}</span>
              <span class="bwf-label text-[var(--color-bwf-text-2)]">{{ m.stageLabel ?? '' }}</span>
            </div>
            <div class="grid gap-0.5 text-sm">
              <span :class="winClass(m, m.teamA?.id)">
                {{ m.teamA?.displayName ?? 'TBD' }}
                <span v-if="m.teamA?.seed" class="text-[var(--color-bwf-muted)]">({{ m.teamA.seed }})</span>
              </span>
              <span :class="winClass(m, m.teamB?.id)">
                {{ m.teamB?.displayName ?? 'TBD' }}
                <span v-if="m.teamB?.seed" class="text-[var(--color-bwf-muted)]">({{ m.teamB.seed }})</span>
              </span>
            </div>
          </div>

          <!-- score -->
          <div class="mt-2 font-mono text-sm sm:mt-0 sm:text-right">
            <span v-if="scoreLine(m)" class="text-[var(--color-bwf-text)]">{{ scoreLine(m) }}</span>
            <span v-else class="text-[var(--color-bwf-muted)]">—</span>
          </div>

          <!-- court -->
          <div class="mt-1 text-xs text-[var(--color-bwf-text-2)] sm:mt-0 sm:text-right">
            {{ m.court?.name ? `Court ${m.court.name}` : '—' }}
          </div>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>
