<script setup lang="ts">
import { computed, defineComponent, h, type PropType } from 'vue';

/**
 * BWF knockout bracket tree (reference image 3) for ONE category.
 *
 * Receives the knockout matches (SEMIFINAL / FINAL / THIRD_PLACE) for a single
 * category — i.e. the subset of MatchDetail rows whose `stage` is a knockout
 * stage. Slots are read from `bracketSlot`:
 *   1 = SF1, 2 = SF2, 3 = Final, 4 = Bronze (THIRD_PLACE).
 *
 * Empty / TBD sides are decoded from teamASource/teamBSource via the same
 * scheme used elsewhere (G:A:1 -> A1, W:1 -> Winner SF1, ...).
 */
export interface BracketTeam {
  id: string;
  displayName: string;
  seed?: number | null;
}
export interface BracketSet {
  teamAScore: number;
  teamBScore: number;
}
export interface BracketMatch {
  id: string;
  status?: string | null;
  stage?: string | null;
  bracketSlot?: number | null;
  teamA?: BracketTeam | null;
  teamB?: BracketTeam | null;
  teamASource?: string | null;
  teamBSource?: string | null;
  winnerId?: string | null;
  sets?: BracketSet[] | null;
}

const props = defineProps<{
  matches: BracketMatch[];
}>();

// Decode a source code into a short human label.
function sourceLabel(code?: string | null): string {
  if (!code) return 'TBD';
  const parts = code.split(':');
  const kind = parts[0];
  if (kind === 'G') {
    const grp = parts[1] ?? '';
    const pos = parts[2] ?? '';
    if (grp) return `${grp}${pos}`;
    const ord = ['', '1st', '2nd', '3rd', '4th'][Number(pos)] ?? `${pos}th`;
    return ord;
  }
  if (kind === 'W') return `Winner SF${parts[1] ?? ''}`;
  if (kind === 'L') return `Loser SF${parts[1] ?? ''}`;
  return code;
}

function slotMatch(n: number): BracketMatch | null {
  return props.matches.find((m) => m.bracketSlot === n) ?? null;
}
const sf1 = computed(() => slotMatch(1));
const sf2 = computed(() => slotMatch(2));
const finalMatch = computed(() => slotMatch(3));
const bronzeMatch = computed(() => slotMatch(4));

const hasBracket = computed(() => props.matches.length > 0);

const championName = computed(() => {
  const f = finalMatch.value;
  if (!f || !f.winnerId) return null;
  if (f.teamA?.id === f.winnerId) return f.teamA.displayName;
  if (f.teamB?.id === f.winnerId) return f.teamB.displayName;
  return null;
});

// Compact two-side node card with green dot + per-game scores.
const BracketNode = defineComponent({
  name: 'BracketNode',
  props: {
    match: { type: Object as PropType<BracketMatch | null>, default: null },
    final: { type: Boolean, default: false },
    bronze: { type: Boolean, default: false },
  },
  setup(p) {
    return () => {
      const m = p.match;
      const live = m?.status === 'IN_PROGRESS';
      const sets = m?.sets ?? [];
      const aWon = !!m?.winnerId && m?.teamA?.id === m.winnerId;
      const bWon = !!m?.winnerId && m?.teamB?.id === m.winnerId;

      const sideName = (team?: BracketTeam | null, src?: string | null) =>
        team?.displayName || sourceLabel(src);

      const dotClass = (winner: boolean) =>
        winner ? 'bwf-dot bwf-dot-win' : live ? 'bwf-dot bwf-dot-live' : 'bwf-dot bwf-dot-idle';

      const renderSide = (
        team: BracketTeam | null | undefined,
        src: string | null | undefined,
        winner: boolean,
        scoreFor: (s: BracketSet) => number,
        scoreAgainst: (s: BracketSet) => number,
      ) =>
        h('div', { class: 'flex items-center gap-2' }, [
          h('span', { class: dotClass(winner) }),
          h(
            'span',
            {
              class: [
                'min-w-0 flex-1 truncate text-[13px]',
                winner
                  ? 'font-semibold text-[var(--color-bwf-text)]'
                  : 'text-[var(--color-bwf-text-2)]',
              ],
            },
            [
              sideName(team, src),
              team?.seed ? h('span', { class: 'bwf-seed' }, ` (${team.seed})`) : null,
            ],
          ),
          h(
            'span',
            { class: 'flex shrink-0 gap-1.5' },
            sets.map((s, i) =>
              h(
                'span',
                {
                  key: i,
                  class: [
                    'text-xs',
                    scoreFor(s) > scoreAgainst(s) ? 'bwf-score-win' : 'bwf-score-loss',
                  ],
                },
                String(scoreFor(s)),
              ),
            ),
          ),
        ]);

      const ringClass = p.bronze
        ? 'ring-amber-700/40'
        : p.final
          ? 'ring-accent-400/40'
          : 'ring-white/5';

      return h('div', { class: ['bwf-surface-raised px-2.5 py-2', `ring-1 ${ringClass}`] }, [
        renderSide(m?.teamA, m?.teamASource, aWon, (s) => s.teamAScore, (s) => s.teamBScore),
        h('div', { class: 'my-1 border-t bwf-hairline' }),
        renderSide(m?.teamB, m?.teamBSource, bWon, (s) => s.teamBScore, (s) => s.teamAScore),
      ]);
    };
  },
});
</script>

<template>
  <div v-if="hasBracket" class="bwf-surface p-4 sm:p-6">
    <!-- ===================== DESKTOP / TABLET TREE ===================== -->
    <div class="hidden gap-5 md:flex md:items-stretch">
      <!-- Semifinals column -->
      <div class="flex flex-1 flex-col">
        <div class="bwf-label mb-3 rounded bg-[var(--color-bwf-raised)] px-2.5 py-1 text-center">
          Semifinals
        </div>
        <div class="flex flex-1 flex-col justify-around gap-6">
          <div class="bwf-bracket-pair bwf-connect">
            <BracketNode :match="sf1" />
          </div>
          <div class="bwf-bracket-pair bwf-connect">
            <BracketNode :match="sf2" />
          </div>
        </div>
      </div>

      <!-- Final column -->
      <div class="flex flex-1 flex-col">
        <div class="bwf-label mb-3 rounded bg-[var(--color-bwf-raised)] px-2.5 py-1 text-center">
          Final
        </div>
        <div class="flex flex-1 flex-col justify-center">
          <div class="bwf-connect-in relative">
            <BracketNode :match="finalMatch" final />
          </div>
        </div>
      </div>

      <!-- Champion column -->
      <div class="flex w-40 flex-col">
        <div class="bwf-label mb-3 rounded bg-[var(--color-bwf-raised)] px-2.5 py-1 text-center">
          Champion
        </div>
        <div class="flex flex-1 flex-col justify-center">
          <div class="champion-card">
            <div class="mb-1 text-2xl">🏆</div>
            <div
              class="text-sm font-bold"
              :class="championName ? 'text-[var(--color-bwf-text)]' : 'text-[var(--color-bwf-muted)]'"
            >
              {{ championName || 'TBD' }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ===================== MOBILE STACK ===================== -->
    <div class="space-y-5 md:hidden">
      <div>
        <div class="bwf-label mb-2">Semifinals</div>
        <div class="space-y-3">
          <BracketNode :match="sf1" />
          <BracketNode :match="sf2" />
        </div>
      </div>
      <div>
        <div class="bwf-label mb-2">Final</div>
        <BracketNode :match="finalMatch" final />
      </div>
      <div class="champion-card">
        <div class="mb-1 text-2xl">🏆</div>
        <div
          class="text-sm font-bold"
          :class="championName ? 'text-[var(--color-bwf-text)]' : 'text-[var(--color-bwf-muted)]'"
        >
          {{ championName || 'TBD' }}
        </div>
      </div>
    </div>

    <!-- ===================== BRONZE (separate block) ===================== -->
    <div v-if="bronzeMatch" class="mt-6 border-t bwf-hairline pt-5">
      <div class="bwf-label mb-2 text-amber-300">Bronze</div>
      <div class="max-w-sm">
        <BracketNode :match="bronzeMatch" bronze />
      </div>
    </div>
  </div>

  <!-- empty state -->
  <div v-else class="bwf-surface px-4 py-10 text-center">
    <p class="text-sm text-[var(--color-bwf-muted)]">Bracket not available yet.</p>
  </div>
</template>
