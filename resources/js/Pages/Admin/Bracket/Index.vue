<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

interface Team {
  id: string;
  displayName: string;
}
interface BracketMatch {
  id: string;
  stage: string | null;
  bracketSlot: number | null;
  categoryId: string | null;
  teamA: Team | null;
  teamB: Team | null;
  teamASource: string | null;
  teamBSource: string | null;
  winnerId: string | null;
  scheduledAt: string | null;
  status: 'SCHEDULED' | 'IN_PROGRESS' | 'COMPLETED' | 'WALKOVER' | string | null;
}
interface Category {
  id: string;
  name: string;
}

const props = defineProps<{
  matches: BracketMatch[];
  categories: Category[];
}>();

const selectedCategoryId = ref<string | null>(
  props.categories.length > 0 ? props.categories[0].id : null,
);

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
    if (grp) return `${grp}${pos}`;
    const ord = ['', '1st', '2nd', '3rd', '4th'][Number(pos)] ?? `${pos}th`;
    return ord;
  }
  if (kind === 'W') return `Winner SF${parts[1] ?? ''}`;
  if (kind === 'L') return `Loser SF${parts[1] ?? ''}`;
  return code;
}

const bracketMatches = computed<BracketMatch[]>(() =>
  props.matches
    .filter((m) => m.categoryId === selectedCategoryId.value)
    .sort((a, b) => (a.bracketSlot ?? 99) - (b.bracketSlot ?? 99)),
);

function matchBySlot(slot: number): BracketMatch | null {
  return bracketMatches.value.find((m) => m.bracketSlot === slot) ?? null;
}
const sf1 = computed(() => matchBySlot(1));
const sf2 = computed(() => matchBySlot(2));
const finalMatch = computed(() => matchBySlot(3));
const bronzeMatch = computed(() => matchBySlot(4));

const championName = computed<string | null>(() => {
  const f = finalMatch.value;
  if (!f || !f.winnerId) return null;
  if (f.teamA?.id === f.winnerId) return f.teamA.displayName;
  if (f.teamB?.id === f.winnerId) return f.teamB.displayName;
  return null;
});

const hasBracket = computed(() => bracketMatches.value.length > 0);

function bracketStatusChip(s: string | null | undefined): string {
  if (s === 'IN_PROGRESS') return 'LIVE';
  if (s === 'COMPLETED' || s === 'WALKOVER') return 'DONE';
  return 'SCHEDULED';
}

// Light-theme chip class for a knockout match status.
function statusChipClass(s: string | null | undefined): string {
  if (s === 'IN_PROGRESS') return 'chip-live';
  if (s === 'COMPLETED' || s === 'WALKOVER') return 'chip-done';
  return 'chip-soon';
}

// Per-team-slot classes for the light bracket: winner highlighted, loser muted.
function slotClass(m: BracketMatch, side: 'A' | 'B'): string {
  const teamId = side === 'A' ? m.teamA?.id : m.teamB?.id;
  if (!m.winnerId) return 'bg-slate-50 ring-slate-200 text-slate-700';
  if (teamId && teamId === m.winnerId) {
    return 'bg-emerald-50 ring-emerald-300 text-emerald-800 font-semibold';
  }
  return 'bg-slate-50 ring-slate-200 text-slate-400';
}

function teamName(m: BracketMatch, side: 'A' | 'B'): string {
  const team = side === 'A' ? m.teamA : m.teamB;
  const src = side === 'A' ? m.teamASource : m.teamBSource;
  return team?.displayName || sourceLabel(src);
}

function timeShort(iso: string | null | undefined): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
  <Head title="Bracket" />
  <AdminLayout>
    <div class="flex items-center justify-between mb-5">
      <h1 class="text-2xl font-bold text-slate-900">Bracket</h1>
      <span class="chip chip-soon">Read-only</span>
    </div>

    <!-- No tournament / no knockout matches at all -->
    <div v-if="props.matches.length === 0" class="card text-center">
      <div class="text-4xl mb-2">🪜</div>
      <p class="text-slate-500">No bracket yet.</p>
      <p class="text-sm text-slate-400 mt-1">
        The knockout tree appears once semifinal matches are generated.
      </p>
    </div>

    <template v-else>
      <!-- Category filter -->
      <div v-if="props.categories.length > 0" class="mb-5">
        <label class="label" for="bracket-category">Category</label>
        <select
          id="bracket-category"
          v-model="selectedCategoryId"
          class="input max-w-xs"
        >
          <option v-for="c in props.categories" :key="c.id" :value="c.id">
            {{ c.name }}
          </option>
        </select>
      </div>

      <!-- No knockout matches in selected category -->
      <div v-if="!hasBracket" class="card text-center">
        <div class="text-3xl mb-2">🪜</div>
        <p class="text-slate-500">No bracket for this category yet.</p>
        <p class="text-sm text-slate-400 mt-1">
          The knockout tree will show once semifinal matches are generated.
        </p>
      </div>

      <!-- Bracket tree (light) -->
      <div v-else class="space-y-6">
        <!-- Main tree: Semifinals -> Final -> Champion -->
        <div class="lg:flex lg:items-stretch lg:gap-4">
          <!-- Column: Semifinals -->
          <div class="lg:flex-1 lg:min-w-[230px]">
            <div class="text-xs uppercase tracking-widest text-slate-400 font-semibold mb-3">
              Semifinals
            </div>
            <div class="flex flex-col gap-4 lg:gap-10 lg:justify-around lg:h-full">
              <template v-for="(sf, idx) in [sf1, sf2]" :key="idx">
                <div v-if="sf" class="card p-0 overflow-hidden">
                  <div class="flex items-center justify-between px-3 py-2 border-b border-slate-100">
                    <span class="text-xs font-bold tracking-wider text-slate-500">
                      SEMIFINAL {{ idx + 1 }}
                    </span>
                    <span :class="statusChipClass(sf.status)">
                      <span v-if="sf.status === 'IN_PROGRESS'" class="inline-block w-1.5 h-1.5 rounded-full bg-live-500 animate-pulse mr-1"></span>
                      {{ bracketStatusChip(sf.status) }}
                    </span>
                  </div>
                  <div class="p-2 space-y-1">
                    <div
                      class="flex items-center justify-between gap-2 px-2.5 py-1.5 rounded-lg ring-1 text-sm"
                      :class="slotClass(sf, 'A')"
                    >
                      <span class="truncate">{{ teamName(sf, 'A') }}</span>
                      <span v-if="sf.winnerId" class="font-mono text-xs shrink-0">
                        {{ sf.teamA && sf.winnerId === sf.teamA.id ? 'W' : '' }}
                      </span>
                    </div>
                    <div
                      class="flex items-center justify-between gap-2 px-2.5 py-1.5 rounded-lg ring-1 text-sm"
                      :class="slotClass(sf, 'B')"
                    >
                      <span class="truncate">{{ teamName(sf, 'B') }}</span>
                      <span v-if="sf.winnerId" class="font-mono text-xs shrink-0">
                        {{ sf.teamB && sf.winnerId === sf.teamB.id ? 'W' : '' }}
                      </span>
                    </div>
                  </div>
                  <div class="px-3 pb-2 text-[11px] text-slate-400">⏱ {{ timeShort(sf.scheduledAt) }}</div>
                </div>
              </template>
            </div>
          </div>

          <!-- Column: Final -->
          <div class="lg:flex-1 lg:min-w-[230px] lg:flex lg:flex-col lg:justify-center mt-4 lg:mt-0">
            <div class="text-xs uppercase tracking-widest text-slate-400 font-semibold mb-3">
              Final
            </div>
            <div v-if="finalMatch" class="card p-0 overflow-hidden ring-accent-400/50">
              <div class="flex items-center justify-between px-3 py-2 border-b border-slate-100 bg-accent-400/10">
                <span class="text-xs font-bold tracking-wider text-amber-700">🏆 FINAL</span>
                <span :class="statusChipClass(finalMatch.status)">
                  <span v-if="finalMatch.status === 'IN_PROGRESS'" class="inline-block w-1.5 h-1.5 rounded-full bg-live-500 animate-pulse mr-1"></span>
                  {{ bracketStatusChip(finalMatch.status) }}
                </span>
              </div>
              <div class="p-2 space-y-1">
                <div
                  class="flex items-center justify-between gap-2 px-2.5 py-1.5 rounded-lg ring-1 text-sm"
                  :class="slotClass(finalMatch, 'A')"
                >
                  <span class="truncate">{{ teamName(finalMatch, 'A') }}</span>
                </div>
                <div
                  class="flex items-center justify-between gap-2 px-2.5 py-1.5 rounded-lg ring-1 text-sm"
                  :class="slotClass(finalMatch, 'B')"
                >
                  <span class="truncate">{{ teamName(finalMatch, 'B') }}</span>
                </div>
              </div>
              <div class="px-3 pb-2 text-[11px] text-slate-400">⏱ {{ timeShort(finalMatch.scheduledAt) }}</div>
            </div>
          </div>

          <!-- Column: Champion -->
          <div class="lg:flex-1 lg:min-w-[180px] lg:flex lg:flex-col lg:justify-center mt-4 lg:mt-0">
            <div
              class="rounded-xl bg-accent-400/10 ring-1 ring-accent-400/40 p-5 text-center"
            >
              <div class="text-4xl mb-1">🏆</div>
              <div class="text-xs tracking-[0.2em] font-bold text-amber-600">CHAMPION</div>
              <div
                class="text-xl font-bold mt-1 leading-tight"
                :class="championName ? 'text-slate-900' : 'text-slate-400'"
              >
                {{ championName || 'TBD' }}
              </div>
            </div>
          </div>
        </div>

        <!-- Bronze final (separate row) -->
        <div v-if="bronzeMatch" class="pt-5 border-t border-slate-200">
          <div class="text-xs uppercase tracking-widest text-slate-400 font-semibold mb-3">
            Bronze Final
          </div>
          <div class="card p-0 overflow-hidden lg:max-w-sm ring-amber-700/30">
            <div class="flex items-center justify-between px-3 py-2 border-b border-slate-100">
              <span class="text-xs font-bold tracking-wider text-amber-700">BRONZE FINAL</span>
              <span :class="statusChipClass(bronzeMatch.status)">
                <span v-if="bronzeMatch.status === 'IN_PROGRESS'" class="inline-block w-1.5 h-1.5 rounded-full bg-live-500 animate-pulse mr-1"></span>
                {{ bracketStatusChip(bronzeMatch.status) }}
              </span>
            </div>
            <div class="p-2 space-y-1">
              <div
                class="flex items-center justify-between gap-2 px-2.5 py-1.5 rounded-lg ring-1 text-sm"
                :class="slotClass(bronzeMatch, 'A')"
              >
                <span class="truncate">{{ teamName(bronzeMatch, 'A') }}</span>
              </div>
              <div
                class="flex items-center justify-between gap-2 px-2.5 py-1.5 rounded-lg ring-1 text-sm"
                :class="slotClass(bronzeMatch, 'B')"
              >
                <span class="truncate">{{ teamName(bronzeMatch, 'B') }}</span>
              </div>
            </div>
            <div class="px-3 pb-2 text-[11px] text-slate-400">⏱ {{ timeShort(bronzeMatch.scheduledAt) }}</div>
          </div>
        </div>
      </div>
    </template>
  </AdminLayout>
</template>
