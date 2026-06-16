<script setup lang="ts">
import { computed } from 'vue';

/**
 * BWF-style match card (reference image 2).
 *
 * The `match` prop is a superset of the backend `serializeMatch()` payload —
 * the Pages phase passes a MatchDetail straight through. Only the keys used
 * below are required; everything is optional/null-safe.
 */
export interface MatchCardPlayer {
  fullName: string;
  club?: string | null;
}
export interface MatchCardTeam {
  id: string;
  displayName: string;
  seed?: number | null;
  players?: MatchCardPlayer[] | null;
}
export interface MatchCardSet {
  teamAScore: number;
  teamBScore: number;
}
export interface MatchCardMatch {
  id: string;
  status?: string | null; // SCHEDULED | IN_PROGRESS | COMPLETED | WALKOVER
  teamA?: MatchCardTeam | null;
  teamB?: MatchCardTeam | null;
  sets?: MatchCardSet[] | null;
  winnerId?: string | null;
  categoryCode?: string | null; // MS/WS/MD/WD/XD
  roundLabel?: string | null; // e.g. "SF", "Group", "Final"
  courtName?: string | null;
  scheduledAt?: string | null;
  startedAt?: string | null;
  completedAt?: string | null;
}

const props = withDefaults(
  defineProps<{
    match: MatchCardMatch;
    matchNumber?: number | null;
    /** 'full' = standalone card (image 2); 'compact' = bracket node */
    variant?: 'full' | 'compact';
    /** override state line text ("Followed By" etc.) */
    stateLabel?: string | null;
  }>(),
  { matchNumber: null, variant: 'full', stateLabel: null },
);

const isCompact = computed(() => props.variant === 'compact');

const isLive = computed(() => props.match.status === 'IN_PROGRESS');
const isDone = computed(
  () => props.match.status === 'COMPLETED' || props.match.status === 'WALKOVER',
);

const sets = computed<MatchCardSet[]>(() => props.match.sets ?? []);

const winnerId = computed(() => props.match.winnerId ?? null);
const teamAWon = computed(
  () => !!winnerId.value && props.match.teamA?.id === winnerId.value,
);
const teamBWon = computed(
  () => !!winnerId.value && props.match.teamB?.id === winnerId.value,
);

function nameLines(team?: MatchCardTeam | null): string[] {
  if (!team) return ['TBD'];
  const players = team.players ?? [];
  if (players.length > 0) return players.map((p) => p.fullName);
  return [team.displayName];
}
function clubLine(team?: MatchCardTeam | null): string | null {
  if (!team) return null;
  const clubs = (team.players ?? [])
    .map((p) => p.club)
    .filter((c): c is string => !!c);
  const uniq = [...new Set(clubs)];
  return uniq.length ? uniq.join(' / ') : null;
}

// ---- date/time formatting (Asia/Yangon) ----
const TZ = 'Asia/Yangon';
function parse(iso?: string | null): Date | null {
  if (!iso) return null;
  const d = new Date(iso);
  return Number.isNaN(d.getTime()) ? null : d;
}
const dateLabel = computed(() => {
  const d = parse(props.match.scheduledAt);
  if (!d) return null;
  return new Intl.DateTimeFormat('en-GB', {
    timeZone: TZ,
    day: '2-digit',
    month: 'short',
  })
    .format(d)
    .toUpperCase(); // "21 JUN"
});
const timeLabel = computed(() => {
  const d = parse(props.match.scheduledAt);
  if (!d) return null;
  return new Intl.DateTimeFormat('en-US', {
    timeZone: TZ,
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  }).format(d); // "1:00 PM"
});

const stateLine = computed(() => {
  if (props.stateLabel) return props.stateLabel;
  if (isLive.value) return 'Live';
  if (isDone.value) return 'Final';
  return 'Starts';
});

// elapsed (live) or duration (done) from started/completed
function fmtDuration(ms: number): string {
  const total = Math.max(0, Math.floor(ms / 1000));
  const h = Math.floor(total / 3600);
  const m = Math.floor((total % 3600) / 60);
  const s = total % 60;
  const mm = String(m).padStart(2, '0');
  const ss = String(s).padStart(2, '0');
  return h > 0 ? `${h}:${mm}:${ss}` : `${mm}:${ss}`;
}
const duration = computed<string | null>(() => {
  const start = parse(props.match.startedAt);
  if (!start) return null;
  const end = parse(props.match.completedAt) ?? (isLive.value ? new Date() : null);
  if (!end) return null;
  return fmtDuration(end.getTime() - start.getTime());
});

const metaRow = computed(() => {
  const parts = [
    props.match.categoryCode,
    props.match.roundLabel,
    props.match.courtName ? `Court ${props.match.courtName}` : null,
  ].filter(Boolean);
  return parts.join('  •  ');
});
</script>

<template>
  <!-- ============================ COMPACT (bracket node) ============================ -->
  <div v-if="isCompact" class="bwf-surface-raised px-2.5 py-2 text-[13px]">
    <!-- side A -->
    <div class="flex items-center gap-2">
      <span
        class="bwf-dot"
        :class="teamAWon ? 'bwf-dot-win' : isLive ? 'bwf-dot-live' : 'bwf-dot-idle'"
      />
      <div class="min-w-0 flex-1 leading-tight">
        <span
          :class="teamAWon ? 'font-semibold text-[var(--color-bwf-text)]' : 'text-[var(--color-bwf-text-2)]'"
          class="truncate block"
        >
          {{ nameLines(match.teamA)[0] }}
          <span v-if="match.teamA?.seed" class="bwf-seed">({{ match.teamA.seed }})</span>
        </span>
      </div>
      <div class="flex gap-1.5">
        <span
          v-for="(s, i) in sets"
          :key="`a${i}`"
          :class="s.teamAScore > s.teamBScore ? 'bwf-score-win' : 'bwf-score-loss'"
          class="text-xs"
        >{{ s.teamAScore }}</span>
      </div>
    </div>
    <div class="my-1 border-t bwf-hairline" />
    <!-- side B -->
    <div class="flex items-center gap-2">
      <span
        class="bwf-dot"
        :class="teamBWon ? 'bwf-dot-win' : isLive ? 'bwf-dot-live' : 'bwf-dot-idle'"
      />
      <div class="min-w-0 flex-1 leading-tight">
        <span
          :class="teamBWon ? 'font-semibold text-[var(--color-bwf-text)]' : 'text-[var(--color-bwf-text-2)]'"
          class="truncate block"
        >
          {{ nameLines(match.teamB)[0] }}
          <span v-if="match.teamB?.seed" class="bwf-seed">({{ match.teamB.seed }})</span>
        </span>
      </div>
      <div class="flex gap-1.5">
        <span
          v-for="(s, i) in sets"
          :key="`b${i}`"
          :class="s.teamBScore > s.teamAScore ? 'bwf-score-win' : 'bwf-score-loss'"
          class="text-xs"
        >{{ s.teamBScore }}</span>
      </div>
    </div>
  </div>

  <!-- ============================ FULL (image 2) ============================ -->
  <div v-else class="bwf-surface relative px-3 pt-3 pb-0 sm:px-4">
    <!-- amber MATCH n pill -->
    <div v-if="matchNumber != null" class="absolute -top-2.5 left-1/2 -translate-x-1/2">
      <span class="bwf-match-pill">Match {{ matchNumber }}</span>
    </div>

    <div
      class="flex flex-col gap-3 pt-1 sm:flex-row sm:items-stretch sm:gap-4"
    >
      <!-- LEFT: date / state / time -->
      <div
        class="flex shrink-0 flex-row items-center justify-center gap-3 text-center sm:w-24 sm:flex-col sm:items-start sm:justify-center sm:text-left"
      >
        <div v-if="dateLabel" class="bwf-label text-[var(--color-bwf-text-2)]">{{ dateLabel }}</div>
        <div
          class="text-[11px] font-semibold uppercase tracking-wide"
          :class="isLive ? 'text-[var(--color-bwf-green)]' : 'text-[var(--color-bwf-muted)]'"
        >
          <span v-if="isLive" class="bwf-dot bwf-dot-live mr-1 align-middle" />{{ stateLine }}
        </div>
        <div v-if="timeLabel" class="text-sm font-medium text-[var(--color-bwf-text)]">{{ timeLabel }}</div>
      </div>

      <!-- divider -->
      <div class="hidden w-px self-stretch bg-[var(--color-bwf-hairline)] sm:block" />

      <!-- CENTER: two sides -->
      <div class="min-w-0 flex-1 space-y-2">
        <!-- side A -->
        <div class="flex items-start gap-2">
          <span
            class="bwf-dot mt-1.5"
            :class="teamAWon ? 'bwf-dot-win' : isLive ? 'bwf-dot-live' : 'bwf-dot-idle'"
          />
          <div class="min-w-0 flex-1">
            <p
              v-for="(ln, i) in nameLines(match.teamA)"
              :key="`an${i}`"
              class="truncate leading-tight"
              :class="teamAWon ? 'font-bold text-[var(--color-bwf-text)]' : isDone ? 'text-[var(--color-bwf-muted)]' : 'text-[var(--color-bwf-text)]'"
            >
              {{ ln }}
              <span
                v-if="i === nameLines(match.teamA).length - 1 && match.teamA?.seed"
                class="bwf-seed"
              >({{ match.teamA.seed }})</span>
            </p>
            <p v-if="clubLine(match.teamA)" class="bwf-label mt-0.5 normal-case tracking-normal">
              {{ clubLine(match.teamA) }}
            </p>
          </div>
        </div>

        <div class="border-t bwf-hairline" />

        <!-- side B -->
        <div class="flex items-start gap-2">
          <span
            class="bwf-dot mt-1.5"
            :class="teamBWon ? 'bwf-dot-win' : isLive ? 'bwf-dot-live' : 'bwf-dot-idle'"
          />
          <div class="min-w-0 flex-1">
            <p
              v-for="(ln, i) in nameLines(match.teamB)"
              :key="`bn${i}`"
              class="truncate leading-tight"
              :class="teamBWon ? 'font-bold text-[var(--color-bwf-text)]' : isDone ? 'text-[var(--color-bwf-muted)]' : 'text-[var(--color-bwf-text)]'"
            >
              {{ ln }}
              <span
                v-if="i === nameLines(match.teamB).length - 1 && match.teamB?.seed"
                class="bwf-seed"
              >({{ match.teamB.seed }})</span>
            </p>
            <p v-if="clubLine(match.teamB)" class="bwf-label mt-0.5 normal-case tracking-normal">
              {{ clubLine(match.teamB) }}
            </p>
          </div>
        </div>
      </div>

      <!-- RIGHT: per-game scores -->
      <div
        v-if="sets.length"
        class="flex shrink-0 items-center justify-center gap-3 sm:justify-end"
      >
        <div class="flex flex-col items-end gap-2">
          <div class="flex gap-3">
            <span
              v-for="(s, i) in sets"
              :key="`sa${i}`"
              class="w-6 text-center"
              :class="s.teamAScore > s.teamBScore ? 'bwf-score-win' : 'bwf-score-loss'"
            >{{ s.teamAScore }}</span>
          </div>
          <div class="flex gap-3">
            <span
              v-for="(s, i) in sets"
              :key="`sb${i}`"
              class="w-6 text-center"
              :class="s.teamBScore > s.teamAScore ? 'bwf-score-win' : 'bwf-score-loss'"
            >{{ s.teamBScore }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- BOTTOM strip -->
    <div
      class="mt-3 flex items-center justify-between border-t bwf-hairline py-2"
    >
      <span class="bwf-label">{{ metaRow }}</span>
      <span v-if="duration" class="bwf-score text-[var(--color-bwf-text-2)]">{{ duration }}</span>
    </div>
  </div>
</template>
