<script setup lang="ts">
import { computed } from 'vue';

interface TeamRef {
  id: string;
  displayName: string;
  seed?: number | null;
}

interface SetScore {
  setNumber: number;
  teamAScore: number;
  teamBScore: number;
  winnerId: string | null;
}

interface MatchDetail {
  id: string;
  stage: string;
  bracketSlot?: number | null;
  winnerId?: string | null;
  teamA?: TeamRef | null;
  teamB?: TeamRef | null;
  sets: SetScore[];
}

const props = defineProps<{
  matches: MatchDetail[];
  highlightWinners?: boolean;
}>();

interface Round {
  stage: string;
  label: string;
  matches: MatchDetail[];
}

const STAGE_ORDER: Record<string, number> = {
  ROUND_OF_64: 1,
  ROUND_OF_32: 2,
  ROUND_OF_16: 3,
  QUARTERFINAL: 4,
  SEMIFINAL: 5,
  FINAL: 6,
  THIRD_PLACE: 7,
};

const STAGE_LABEL: Record<string, string> = {
  ROUND_OF_64: 'Round of 64',
  ROUND_OF_32: 'Round of 32',
  ROUND_OF_16: 'Round of 16',
  QUARTERFINAL: 'Quarterfinals',
  SEMIFINAL: 'Semifinals',
  FINAL: 'Final',
  THIRD_PLACE: '3rd Place',
};

// All rounds (excluding GROUP and THIRD_PLACE which we render separately)
const rounds = computed<Round[]>(() => {
  const knockout = props.matches.filter(
    (m) => m.stage !== 'GROUP' && m.stage !== 'THIRD_PLACE',
  );
  const byStage = new Map<string, MatchDetail[]>();
  for (const m of knockout) {
    const arr = byStage.get(m.stage) ?? [];
    arr.push(m);
    byStage.set(m.stage, arr);
  }
  const result: Round[] = [];
  for (const [stage, ms] of byStage) {
    result.push({
      stage,
      label: STAGE_LABEL[stage] ?? stage,
      matches: ms.sort((a, b) => (a.bracketSlot ?? 0) - (b.bracketSlot ?? 0)),
    });
  }
  result.sort((a, b) => (STAGE_ORDER[a.stage] ?? 99) - (STAGE_ORDER[b.stage] ?? 99));
  return result;
});

// Left half: first half of each round (left-most → SF[slot 1])
const leftRounds = computed<Round[]>(() =>
  rounds.value
    .filter((r) => r.stage !== 'FINAL')
    .map((r) => {
      const half = Math.ceil(r.matches.length / 2);
      return { ...r, matches: r.matches.slice(0, half) };
    }),
);

// Right half: second half of each round, reversed so left-most → SF[slot 2]
const rightRounds = computed<Round[]>(() =>
  [...rounds.value]
    .filter((r) => r.stage !== 'FINAL')
    .reverse()
    .map((r) => {
      const half = Math.ceil(r.matches.length / 2);
      return { ...r, matches: r.matches.slice(half) };
    }),
);

const finalMatch = computed<MatchDetail | null>(
  () => rounds.value.find((r) => r.stage === 'FINAL')?.matches[0] ?? null,
);

const thirdPlaceMatch = computed<MatchDetail | null>(
  () => props.matches.find((m) => m.stage === 'THIRD_PLACE') ?? null,
);

const champion = computed(() => {
  const f = finalMatch.value;
  if (!f?.winnerId) return null;
  if (f.teamA?.id === f.winnerId) return f.teamA;
  if (f.teamB?.id === f.winnerId) return f.teamB;
  return null;
});

function setsWon(m: MatchDetail, teamId: string | null | undefined) {
  if (!teamId) return null;
  const wins = m.sets.filter((s) => s.winnerId === teamId).length;
  return m.sets.length > 0 ? wins : null;
}

function isWinner(m: MatchDetail, teamId: string | null | undefined) {
  return !!props.highlightWinners && !!teamId && m.winnerId === teamId;
}

function isLoser(m: MatchDetail, teamId: string | null | undefined) {
  return (
    !!props.highlightWinners &&
    !!teamId &&
    !!m.winnerId &&
    m.winnerId !== teamId
  );
}
</script>

<template>
  <div v-if="rounds.length === 0" class="rounded-2xl bg-slate-900 text-slate-300 text-center py-12">
    <div class="text-5xl mb-3">🏆</div>
    <p>No knockout matches yet.</p>
  </div>

  <div v-else class="bracket-stage relative overflow-hidden rounded-2xl">
    <div class="bracket-pattern" aria-hidden="true"></div>

    <!-- Title banner -->
    <div class="relative z-10 flex justify-center pt-6 sm:pt-8 pb-2">
      <div class="bracket-banner">TOURNAMENT BRACKET</div>
    </div>

    <!-- Desktop/Tablet mirrored layout -->
    <div class="bracket-body hidden md:grid">
      <!-- LEFT HALF -->
      <div class="bracket-half">
        <div v-for="round in leftRounds" :key="`l-${round.stage}`" class="bracket-round">
          <div class="bracket-round-label">{{ round.label }}</div>
          <div class="bracket-round-matches">
            <div v-for="m in round.matches" :key="m.id" class="bracket-match">
              <div
                class="team-pill"
                :class="{ winner: isWinner(m, m.teamA?.id), loser: isLoser(m, m.teamA?.id) }"
              >
                <span v-if="m.teamA?.seed" class="seed">{{ m.teamA.seed }}</span>
                <span class="name">{{ m.teamA?.displayName || 'TBD' }}</span>
                <span v-if="setsWon(m, m.teamA?.id) != null" class="score">{{ setsWon(m, m.teamA?.id) }}</span>
              </div>
              <div
                class="team-pill"
                :class="{ winner: isWinner(m, m.teamB?.id), loser: isLoser(m, m.teamB?.id) }"
              >
                <span v-if="m.teamB?.seed" class="seed">{{ m.teamB.seed }}</span>
                <span class="name">{{ m.teamB?.displayName || 'TBD' }}</span>
                <span v-if="setsWon(m, m.teamB?.id) != null" class="score">{{ setsWon(m, m.teamB?.id) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- CENTER: Trophy + Final -->
      <div class="bracket-center">
        <div class="trophy" :class="{ won: !!champion }">
          <span class="trophy-icon">🏆</span>
          <div v-if="champion" class="champion-name">{{ champion.displayName }}</div>
        </div>
        <div v-if="finalMatch" class="bracket-match final">
          <div class="bracket-round-label center">Final</div>
          <div
            class="team-pill final-pill"
            :class="{ winner: isWinner(finalMatch, finalMatch.teamA?.id), loser: isLoser(finalMatch, finalMatch.teamA?.id) }"
          >
            <span v-if="finalMatch.teamA?.seed" class="seed">{{ finalMatch.teamA.seed }}</span>
            <span class="name">{{ finalMatch.teamA?.displayName || 'TBD' }}</span>
            <span v-if="setsWon(finalMatch, finalMatch.teamA?.id) != null" class="score">
              {{ setsWon(finalMatch, finalMatch.teamA?.id) }}
            </span>
          </div>
          <div
            class="team-pill final-pill"
            :class="{ winner: isWinner(finalMatch, finalMatch.teamB?.id), loser: isLoser(finalMatch, finalMatch.teamB?.id) }"
          >
            <span v-if="finalMatch.teamB?.seed" class="seed">{{ finalMatch.teamB.seed }}</span>
            <span class="name">{{ finalMatch.teamB?.displayName || 'TBD' }}</span>
            <span v-if="setsWon(finalMatch, finalMatch.teamB?.id) != null" class="score">
              {{ setsWon(finalMatch, finalMatch.teamB?.id) }}
            </span>
          </div>
        </div>
      </div>

      <!-- RIGHT HALF -->
      <div class="bracket-half mirror">
        <div v-for="round in rightRounds" :key="`r-${round.stage}`" class="bracket-round">
          <div class="bracket-round-label">{{ round.label }}</div>
          <div class="bracket-round-matches">
            <div v-for="m in round.matches" :key="m.id" class="bracket-match">
              <div
                class="team-pill"
                :class="{ winner: isWinner(m, m.teamA?.id), loser: isLoser(m, m.teamA?.id) }"
              >
                <span v-if="m.teamA?.seed" class="seed">{{ m.teamA.seed }}</span>
                <span class="name">{{ m.teamA?.displayName || 'TBD' }}</span>
                <span v-if="setsWon(m, m.teamA?.id) != null" class="score">{{ setsWon(m, m.teamA?.id) }}</span>
              </div>
              <div
                class="team-pill"
                :class="{ winner: isWinner(m, m.teamB?.id), loser: isLoser(m, m.teamB?.id) }"
              >
                <span v-if="m.teamB?.seed" class="seed">{{ m.teamB.seed }}</span>
                <span class="name">{{ m.teamB?.displayName || 'TBD' }}</span>
                <span v-if="setsWon(m, m.teamB?.id) != null" class="score">{{ setsWon(m, m.teamB?.id) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Mobile: stacked single column -->
    <div class="bracket-mobile md:hidden relative z-10 px-4 pb-6 pt-2 space-y-5">
      <div v-for="round in rounds" :key="`m-${round.stage}`">
        <div class="bracket-round-label center mb-2">{{ round.label }}</div>
        <div class="space-y-3">
          <div v-for="m in round.matches" :key="m.id" class="bracket-match">
            <div
              class="team-pill"
              :class="{ winner: isWinner(m, m.teamA?.id), loser: isLoser(m, m.teamA?.id) }"
            >
              <span v-if="m.teamA?.seed" class="seed">{{ m.teamA.seed }}</span>
              <span class="name">{{ m.teamA?.displayName || 'TBD' }}</span>
              <span v-if="setsWon(m, m.teamA?.id) != null" class="score">{{ setsWon(m, m.teamA?.id) }}</span>
            </div>
            <div
              class="team-pill"
              :class="{ winner: isWinner(m, m.teamB?.id), loser: isLoser(m, m.teamB?.id) }"
            >
              <span v-if="m.teamB?.seed" class="seed">{{ m.teamB.seed }}</span>
              <span class="name">{{ m.teamB?.displayName || 'TBD' }}</span>
              <span v-if="setsWon(m, m.teamB?.id) != null" class="score">{{ setsWon(m, m.teamB?.id) }}</span>
            </div>
          </div>
        </div>
      </div>
      <div v-if="champion" class="text-center py-4">
        <div class="text-5xl">🏆</div>
        <div class="text-amber-400 font-bold mt-2 text-lg">{{ champion.displayName }}</div>
      </div>
    </div>

    <!-- 3rd place playoff (separate card at the bottom) -->
    <div v-if="thirdPlaceMatch" class="relative z-10 px-4 pb-6 pt-2">
      <div class="bracket-third-place mx-auto">
        <div class="bracket-round-label center mb-2">3rd place</div>
        <div class="bracket-match">
          <div
            class="team-pill bronze"
            :class="{
              winner: isWinner(thirdPlaceMatch, thirdPlaceMatch.teamA?.id),
              loser: isLoser(thirdPlaceMatch, thirdPlaceMatch.teamA?.id),
            }"
          >
            <span v-if="thirdPlaceMatch.teamA?.seed" class="seed">{{ thirdPlaceMatch.teamA.seed }}</span>
            <span class="name">{{ thirdPlaceMatch.teamA?.displayName || 'TBD' }}</span>
            <span v-if="setsWon(thirdPlaceMatch, thirdPlaceMatch.teamA?.id) != null" class="score">
              {{ setsWon(thirdPlaceMatch, thirdPlaceMatch.teamA?.id) }}
            </span>
          </div>
          <div
            class="team-pill bronze"
            :class="{
              winner: isWinner(thirdPlaceMatch, thirdPlaceMatch.teamB?.id),
              loser: isLoser(thirdPlaceMatch, thirdPlaceMatch.teamB?.id),
            }"
          >
            <span v-if="thirdPlaceMatch.teamB?.seed" class="seed">{{ thirdPlaceMatch.teamB.seed }}</span>
            <span class="name">{{ thirdPlaceMatch.teamB?.displayName || 'TBD' }}</span>
            <span v-if="setsWon(thirdPlaceMatch, thirdPlaceMatch.teamB?.id) != null" class="score">
              {{ setsWon(thirdPlaceMatch, thirdPlaceMatch.teamB?.id) }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ============= Stage container ============= */
.bracket-stage {
  background:
    radial-gradient(1000px 600px at 50% -20%, rgba(99, 102, 241, 0.35), transparent 60%),
    radial-gradient(800px 500px at 110% 110%, rgba(16, 185, 129, 0.2), transparent 60%),
    linear-gradient(135deg, #050816 0%, #0f172a 50%, #1e1b4b 100%);
}

.bracket-pattern {
  position: absolute;
  inset: 0;
  background:
    repeating-linear-gradient(
      45deg,
      transparent 0,
      transparent 60px,
      rgba(255, 255, 255, 0.025) 60px,
      rgba(255, 255, 255, 0.025) 61px
    ),
    repeating-linear-gradient(
      -45deg,
      transparent 0,
      transparent 120px,
      rgba(99, 102, 241, 0.04) 120px,
      rgba(99, 102, 241, 0.04) 121px
    );
  pointer-events: none;
}

/* ============= Banner ============= */
.bracket-banner {
  background: linear-gradient(135deg, #3b82f6 0%, #6366f1 60%, #8b5cf6 100%);
  color: white;
  padding: 0.75rem 2rem;
  border-radius: 9999px;
  font-weight: 800;
  font-size: 0.875rem;
  letter-spacing: 0.2em;
  box-shadow:
    0 0 24px rgba(99, 102, 241, 0.5),
    inset 0 1px 0 rgba(255, 255, 255, 0.25),
    inset 0 -1px 0 rgba(0, 0, 0, 0.2);
}

@media (min-width: 768px) {
  .bracket-banner {
    font-size: 1rem;
    padding: 0.875rem 2.5rem;
  }
}

/* ============= Bracket body grid ============= */
.bracket-body {
  position: relative;
  z-index: 10;
  grid-template-columns: 1fr auto 1fr;
  gap: 1.25rem;
  padding: 1.5rem 1.5rem 2rem;
  align-items: stretch;
}

.bracket-half {
  display: flex;
  gap: 1.25rem;
}
.bracket-half.mirror {
  flex-direction: row-reverse;
}

.bracket-round {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 9rem;
}

.bracket-round-label {
  color: rgba(255, 255, 255, 0.5);
  font-size: 0.625rem;
  text-transform: uppercase;
  letter-spacing: 0.18em;
  font-weight: 700;
  text-align: center;
  margin-bottom: 0.75rem;
}
.bracket-round-label.center {
  color: rgba(255, 255, 255, 0.7);
}

.bracket-round-matches {
  display: flex;
  flex-direction: column;
  justify-content: space-around;
  flex: 1;
  gap: 0.5rem;
}

/* ============= Match (two pills stacked) ============= */
.bracket-match {
  display: flex;
  flex-direction: column;
  gap: 4px;
  position: relative;
}

/* Right-side horizontal connector */
.bracket-half:not(.mirror) .bracket-round:not(:last-child) .bracket-match::after {
  content: '';
  position: absolute;
  right: -0.7rem;
  top: 50%;
  width: 0.7rem;
  height: 2px;
  background: rgba(255, 255, 255, 0.35);
}
/* Mirror side horizontal connector (on left) */
.bracket-half.mirror .bracket-round:not(:last-child) .bracket-match::after {
  content: '';
  position: absolute;
  left: -0.7rem;
  top: 50%;
  width: 0.7rem;
  height: 2px;
  background: rgba(255, 255, 255, 0.35);
}

/* Vertical connector between match pairs (joining sibling matches into next round) */
.bracket-half:not(.mirror) .bracket-round:not(:last-child) .bracket-match:nth-child(odd)::before {
  content: '';
  position: absolute;
  right: -0.7rem;
  top: 50%;
  width: 2px;
  height: calc(100% + 0.5rem);
  background: rgba(255, 255, 255, 0.35);
}
.bracket-half.mirror .bracket-round:not(:last-child) .bracket-match:nth-child(odd)::before {
  content: '';
  position: absolute;
  left: -0.7rem;
  top: 50%;
  width: 2px;
  height: calc(100% + 0.5rem);
  background: rgba(255, 255, 255, 0.35);
}

/* ============= Team pill ============= */
.team-pill {
  background: linear-gradient(135deg, #1e40af 0%, #2563eb 35%, #3b82f6 70%, #6366f1 100%);
  color: white;
  border-radius: 9999px;
  padding: 0.45rem 0.85rem;
  font-weight: 700;
  font-size: 0.75rem;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  box-shadow:
    0 0 16px rgba(59, 130, 246, 0.45),
    inset 0 1px 0 rgba(255, 255, 255, 0.25),
    inset 0 -1px 0 rgba(0, 0, 0, 0.15);
  min-height: 30px;
  transition: all 0.2s ease;
}

.team-pill .seed {
  background: rgba(255, 255, 255, 0.2);
  border-radius: 9999px;
  padding: 0 0.4rem;
  font-size: 0.65rem;
  font-weight: 800;
  min-width: 1.2rem;
  text-align: center;
}

.team-pill .name {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.team-pill .score {
  font-family: 'JetBrains Mono', monospace;
  background: rgba(0, 0, 0, 0.3);
  border-radius: 9999px;
  padding: 0 0.4rem;
  font-size: 0.7rem;
  min-width: 1.2rem;
  text-align: center;
}

.team-pill.winner {
  background: linear-gradient(135deg, #047857 0%, #059669 35%, #10b981 70%, #34d399 100%);
  box-shadow:
    0 0 24px rgba(16, 185, 129, 0.6),
    inset 0 1px 0 rgba(255, 255, 255, 0.3),
    inset 0 -1px 0 rgba(0, 0, 0, 0.2);
}

.team-pill.loser {
  background: linear-gradient(135deg, #334155 0%, #475569 100%);
  opacity: 0.5;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

.team-pill.bronze {
  background: linear-gradient(135deg, #92400e 0%, #b45309 35%, #d97706 70%, #f59e0b 100%);
  box-shadow:
    0 0 16px rgba(217, 119, 6, 0.5),
    inset 0 1px 0 rgba(255, 255, 255, 0.25);
}
.team-pill.bronze.winner {
  background: linear-gradient(135deg, #92400e 0%, #d97706 50%, #fbbf24 100%);
  box-shadow:
    0 0 24px rgba(251, 191, 36, 0.7),
    inset 0 1px 0 rgba(255, 255, 255, 0.35);
}

/* ============= Center: Trophy + Final ============= */
.bracket-center {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  padding: 0 1rem;
  min-width: 11rem;
}

.trophy {
  text-align: center;
  filter: grayscale(0.2) opacity(0.85);
  transition: filter 0.4s ease;
}
.trophy.won {
  filter: grayscale(0) opacity(1) drop-shadow(0 0 20px rgba(251, 191, 36, 0.7));
  animation: trophy-glow 2.5s ease-in-out infinite;
}

.trophy-icon {
  font-size: 3.5rem;
  display: inline-block;
}

.champion-name {
  margin-top: 0.5rem;
  color: #fbbf24;
  font-weight: 800;
  font-size: 0.85rem;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  text-shadow: 0 0 12px rgba(251, 191, 36, 0.6);
}

.bracket-match.final {
  width: 100%;
}

.final-pill {
  padding: 0.6rem 1rem;
  font-size: 0.85rem;
}

@keyframes trophy-glow {
  0%, 100% { filter: opacity(1) drop-shadow(0 0 14px rgba(251, 191, 36, 0.6)); }
  50% { filter: opacity(1) drop-shadow(0 0 24px rgba(251, 191, 36, 0.9)); }
}

/* ============= 3rd place ============= */
.bracket-third-place {
  max-width: 22rem;
  background: rgba(0, 0, 0, 0.25);
  border-radius: 1rem;
  padding: 1rem 1.25rem;
  border: 1px solid rgba(217, 119, 6, 0.25);
}
</style>
