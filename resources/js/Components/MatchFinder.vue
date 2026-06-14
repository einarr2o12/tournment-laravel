<script setup lang="ts">
import { computed, ref } from 'vue';

interface PlayerRef {
  fullName: string;
}

interface TeamRef {
  id: string;
  displayName: string;
  players?: PlayerRef[];
}

interface MatchDetail {
  id: string;
  status: string;
  stage: string;
  categoryName?: string | null;
  categoryType?: string | null;
  scheduledAt?: string | null;
  court?: { id: string; name: string } | null;
  teamA?: TeamRef | null;
  teamB?: TeamRef | null;
}

const props = defineProps<{
  matches: MatchDetail[];
  /** Labels (pass in for i18n). Defaults to English. */
  title?: string;
  hint?: string;
  placeholder?: string;
  liveLabel?: string;
  scheduledLabel?: string;
  completedLabel?: string;
  walkoverLabel?: string;
  noResultsLabel?: string;
  courtLabel?: string;
}>();

const titleText = computed(() => props.title ?? '🔎 Find your match');
const hintText = computed(() => props.hint ?? 'Type your name or club to see when and where you play.');
const placeholderText = computed(() => props.placeholder ?? 'e.g. Smith');
const liveLabelText = computed(() => props.liveLabel ?? 'Live');
const scheduledLabelText = computed(() => props.scheduledLabel ?? 'Scheduled');
const completedLabelText = computed(() => props.completedLabel ?? 'Completed');
const walkoverLabelText = computed(() => props.walkoverLabel ?? 'Walkover');
const courtLabelText = computed(() => props.courtLabel ?? 'Court');

const query = ref('');

const results = computed(() => {
  const q = query.value.trim().toLowerCase();
  if (q.length < 2) return [];
  return props.matches
    .filter((m) => {
      const a = m.teamA?.displayName?.toLowerCase() ?? '';
      const b = m.teamB?.displayName?.toLowerCase() ?? '';
      const players = [
        ...(m.teamA?.players ?? []),
        ...(m.teamB?.players ?? []),
      ]
        .map((p) => p.fullName.toLowerCase())
        .join(' ');
      return a.includes(q) || b.includes(q) || players.includes(q);
    })
    .sort((x, y) => {
      // Live first, then scheduled, then completed (latest first)
      const score = (m: MatchDetail) =>
        m.status === 'IN_PROGRESS' ? 0 : m.status === 'SCHEDULED' ? 1 : 2;
      const sx = score(x);
      const sy = score(y);
      if (sx !== sy) return sx - sy;
      const tx = x.scheduledAt ? new Date(x.scheduledAt).getTime() : Number.MAX_SAFE_INTEGER;
      const ty = y.scheduledAt ? new Date(y.scheduledAt).getTime() : Number.MAX_SAFE_INTEGER;
      return tx - ty;
    });
});

function formatTime(iso: string | null | undefined) {
  if (!iso) return null;
  const d = new Date(iso);
  return d.toLocaleString([], {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function statusChip(m: MatchDetail) {
  switch (m.status) {
    case 'IN_PROGRESS':
      return { cls: 'chip-live', text: liveLabelText.value };
    case 'COMPLETED':
      return { cls: 'chip-done', text: completedLabelText.value };
    case 'WALKOVER':
      return { cls: 'chip-walkover', text: walkoverLabelText.value };
    default:
      return { cls: 'chip-soon', text: scheduledLabelText.value };
  }
}
</script>

<template>
  <section class="rounded-2xl bg-white ring-1 ring-slate-200 p-5 sm:p-6 shadow-sm">
    <label for="find-match" class="block text-sm font-semibold text-slate-700">
      {{ titleText }}
    </label>
    <p class="text-xs text-slate-500 mb-3">
      {{ hintText }}
    </p>
    <input
      id="find-match"
      v-model="query"
      type="search"
      class="input text-base py-3"
      :placeholder="placeholderText"
      autocomplete="off"
    />

    <div v-if="query.trim().length >= 2" class="mt-4 space-y-2">
      <div v-if="results.length === 0" class="text-sm text-slate-500 text-center py-6">
        {{ props.noResultsLabel ?? `No matches found for "${query}".` }}
      </div>
      <div
        v-for="m in results.slice(0, 8)"
        :key="m.id"
        class="flex items-center gap-4 rounded-lg border border-slate-200 px-3 py-3"
      >
        <div class="text-center shrink-0 min-w-[64px]">
          <div class="text-display text-2xl text-brand-700 leading-none">
            {{ m.court?.name?.replace(/^Court\s*/i, '') || '—' }}
          </div>
          <div class="text-[10px] uppercase tracking-widest text-slate-400 mt-1">{{ courtLabelText }}</div>
        </div>
        <div class="flex-1 min-w-0">
          <div class="text-xs uppercase tracking-wider text-slate-400">
            {{ m.categoryName || m.categoryType }} · {{ m.stage }}
          </div>
          <div class="font-semibold text-slate-900 truncate">
            {{ m.teamA?.displayName || 'TBD' }}
            <span class="text-slate-400 mx-1">vs</span>
            {{ m.teamB?.displayName || 'TBD' }}
          </div>
          <div v-if="m.scheduledAt" class="text-xs text-slate-500 mt-0.5">
            ⏱ {{ formatTime(m.scheduledAt) }}
          </div>
        </div>
        <span :class="statusChip(m).cls">{{ statusChip(m).text }}</span>
      </div>
      <div v-if="results.length > 8" class="text-xs text-slate-500 text-center pt-1">
        +{{ results.length - 8 }} more
      </div>
    </div>
  </section>
</template>
