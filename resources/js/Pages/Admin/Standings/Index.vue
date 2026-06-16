<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

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
interface Category {
  id: string;
  name: string;
}

const props = defineProps<{
  standings: GroupStanding[];
  categories: Category[];
}>();

// Default to the first category (if any) so the filter is never empty.
const selectedCategoryId = ref<string | null>(
  props.categories.length > 0 ? props.categories[0].id : null,
);

const standingsForCategory = computed<GroupStanding[]>(() => {
  if (!selectedCategoryId.value) return props.standings;
  return props.standings.filter((g) => g.categoryId === selectedCategoryId.value);
});

// A group has "no results yet" when every row is all-zero (no completed match
// has touched it). Rows arrive pre-ranked from the backend (GroupStandings),
// so we render them in order and only need this flag for the empty state.
function hasResults(g: GroupStanding): boolean {
  return g.rows.some((r) => r.played > 0);
}

function diff(r: StandingRow): number {
  return r.pointsFor - r.pointsAgainst;
}
</script>

<template>
  <Head title="Standings" />
  <AdminLayout>
    <div class="flex items-center justify-between mb-5">
      <h1 class="text-2xl font-bold text-slate-900">Standings</h1>
      <span class="chip chip-soon">Read-only</span>
    </div>

    <!-- No tournament / no groups at all -->
    <div v-if="props.standings.length === 0" class="card text-center">
      <div class="text-4xl mb-2">📈</div>
      <p class="text-slate-500">No standings yet.</p>
      <p class="text-sm text-slate-400 mt-1">
        Standings appear once groups are drawn and match results are entered.
      </p>
    </div>

    <template v-else>
      <!-- Category filter -->
      <div v-if="props.categories.length > 0" class="mb-5">
        <label class="label" for="standings-category">Category</label>
        <select
          id="standings-category"
          v-model="selectedCategoryId"
          class="input max-w-xs"
        >
          <option v-for="c in props.categories" :key="c.id" :value="c.id">
            {{ c.name }}
          </option>
        </select>
      </div>

      <!-- No groups in the selected category -->
      <div v-if="standingsForCategory.length === 0" class="card text-center">
        <p class="text-slate-500">No groups in this category.</p>
      </div>

      <div v-else class="space-y-5">
        <div
          v-for="g in standingsForCategory"
          :key="g.groupId"
          class="card p-0 overflow-hidden"
        >
          <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
            <h2 class="font-semibold text-slate-900">{{ g.name }}</h2>
            <span class="chip chip-done">{{ g.rows.length }} {{ g.rows.length === 1 ? 'team' : 'teams' }}</span>
          </div>

          <!-- Empty state: group exists but no results entered yet -->
          <div v-if="!hasResults(g)" class="px-4 py-8 text-center">
            <div class="text-3xl mb-2">⏳</div>
            <p class="text-sm text-slate-500">
              No results yet — standings update as match results are entered.
            </p>
            <ul v-if="g.rows.length" class="mt-4 inline-flex flex-wrap justify-center gap-2">
              <li
                v-for="r in g.rows"
                :key="r.teamId"
                class="chip chip-done"
              >
                {{ r.teamName || 'Unnamed team' }}
              </li>
            </ul>
          </div>

          <!-- Standings table -->
          <div v-else class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-slate-50">
                <tr class="text-xs uppercase tracking-wider text-slate-400">
                  <th class="text-left py-2 px-4">#</th>
                  <th class="text-left py-2 px-4">Team</th>
                  <th class="text-right py-2 px-3">P</th>
                  <th class="text-right py-2 px-3">W</th>
                  <th class="text-right py-2 px-3">L</th>
                  <th class="text-right py-2 px-3">Sets</th>
                  <th class="text-right py-2 px-3">Pts</th>
                  <th class="text-right py-2 px-4">Diff</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(r, i) in g.rows"
                  :key="r.teamId"
                  class="border-t border-slate-100"
                  :class="i === 0 ? 'bg-brand-50/40' : ''"
                >
                  <td class="py-2 px-4 font-semibold" :class="i === 0 ? 'text-brand-700' : 'text-slate-400'">
                    {{ i + 1 }}
                  </td>
                  <td class="py-2 px-4 font-medium text-slate-800">
                    {{ r.teamName || 'Unnamed team' }}
                  </td>
                  <td class="py-2 px-3 text-right text-slate-600">{{ r.played }}</td>
                  <td class="py-2 px-3 text-right text-slate-600">{{ r.won }}</td>
                  <td class="py-2 px-3 text-right text-slate-600">{{ r.lost }}</td>
                  <td class="py-2 px-3 text-right text-slate-600">
                    {{ r.setsFor }}-{{ r.setsAgainst }}
                  </td>
                  <td class="py-2 px-3 text-right font-mono font-semibold text-slate-800">
                    {{ r.pointsFor }}
                  </td>
                  <td
                    class="py-2 px-4 text-right font-mono"
                    :class="
                      diff(r) > 0
                        ? 'text-emerald-600'
                        : diff(r) < 0
                          ? 'text-rose-600'
                          : 'text-slate-500'
                    "
                  >
                    {{ diff(r) > 0 ? '+' : '' }}{{ diff(r) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </template>
  </AdminLayout>
</template>
