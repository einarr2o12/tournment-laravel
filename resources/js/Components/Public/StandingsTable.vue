<script setup lang="ts">
import { computed } from 'vue';

/**
 * BWF dark standings table for ONE group (reference image 1 aesthetic).
 *
 * Ranking is wins -> total points scored -> point differential (the backend
 * already returns rows in rank order; this component does not re-sort, it only
 * displays).
 */
export interface StandingRow {
  teamId: string;
  teamName: string;
  played: number;
  won: number;
  lost: number;
  setsFor?: number;
  setsAgainst?: number;
  pointsFor?: number;
  pointsAgainst?: number;
}

const props = withDefaults(
  defineProps<{
    name: string;
    rows: StandingRow[];
    /** show the Sets column (minimal by default) */
    showSets?: boolean;
    /** number of top rows to highlight as qualifying */
    qualifyCount?: number;
  }>(),
  { showSets: false, qualifyCount: 0 },
);

function diff(r: StandingRow): number {
  return (r.pointsFor ?? 0) - (r.pointsAgainst ?? 0);
}
function diffLabel(r: StandingRow): string {
  const d = diff(r);
  return d > 0 ? `+${d}` : `${d}`;
}

const hasRows = computed(() => props.rows.length > 0);
</script>

<template>
  <div class="bwf-surface overflow-hidden">
    <!-- group header -->
    <div class="flex items-center justify-between border-b bwf-hairline px-4 py-2.5">
      <span class="bwf-label text-[var(--color-bwf-text-2)]">{{ name }}</span>
      <span class="bwf-label">{{ rows.length }} Teams</span>
    </div>

    <table v-if="hasRows" class="w-full text-sm">
      <thead>
        <tr class="bwf-label border-b bwf-hairline">
          <th class="w-8 py-2 pl-4 text-left font-semibold">#</th>
          <th class="py-2 text-left font-semibold">Team</th>
          <th class="w-10 py-2 text-center font-semibold">P</th>
          <th class="w-10 py-2 text-center font-semibold">W</th>
          <th class="w-10 py-2 text-center font-semibold">L</th>
          <th v-if="showSets" class="w-14 py-2 text-center font-semibold">Sets</th>
          <th class="w-12 py-2 text-center font-semibold">Pts</th>
          <th class="w-14 py-2 pr-4 text-right font-semibold">Diff</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(r, i) in rows"
          :key="r.teamId"
          class="border-b bwf-hairline last:border-0"
          :class="i < qualifyCount ? 'bg-[var(--color-bwf-green)]/5' : ''"
        >
          <td class="py-2.5 pl-4">
            <span
              class="inline-flex h-5 w-5 items-center justify-center rounded font-mono text-xs"
              :class="i < qualifyCount
                ? 'bg-[var(--color-bwf-green)]/20 text-[var(--color-bwf-green)]'
                : 'text-[var(--color-bwf-muted)]'"
            >{{ i + 1 }}</span>
          </td>
          <td class="py-2.5 pr-2">
            <span class="font-medium text-[var(--color-bwf-text)]">{{ r.teamName }}</span>
          </td>
          <td class="py-2.5 text-center font-mono text-[var(--color-bwf-text-2)]">{{ r.played }}</td>
          <td class="py-2.5 text-center font-mono font-semibold text-[var(--color-bwf-text)]">{{ r.won }}</td>
          <td class="py-2.5 text-center font-mono text-[var(--color-bwf-text-2)]">{{ r.lost }}</td>
          <td v-if="showSets" class="py-2.5 text-center font-mono text-[var(--color-bwf-text-2)]">
            {{ (r.setsFor ?? 0) }}-{{ (r.setsAgainst ?? 0) }}
          </td>
          <td class="py-2.5 text-center font-mono font-semibold text-[var(--color-bwf-text)]">
            {{ r.pointsFor ?? 0 }}
          </td>
          <td class="py-2.5 pr-4 text-right">
            <span
              class="font-mono text-xs font-semibold"
              :class="diff(r) > 0
                ? 'text-[var(--color-bwf-green)]'
                : diff(r) < 0
                  ? 'text-[var(--color-bwf-red)]'
                  : 'text-[var(--color-bwf-muted)]'"
            >{{ diffLabel(r) }}</span>
          </td>
        </tr>
      </tbody>
    </table>

    <div v-else class="px-4 py-8 text-center text-sm text-[var(--color-bwf-muted)]">
      No standings yet.
    </div>
  </div>
</template>
