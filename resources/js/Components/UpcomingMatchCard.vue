<script setup lang="ts">
import { computed } from 'vue';

interface TeamRef {
  id: string;
  displayName: string;
}

interface MatchDetail {
  id: string;
  stage: string;
  categoryName?: string | null;
  categoryType?: string | null;
  scheduledAt?: string | null;
  court?: { id: string; name: string } | null;
  teamA?: TeamRef | null;
  teamB?: TeamRef | null;
}

const props = defineProps<{ match: MatchDetail }>();

const timeLabel = computed(() => {
  if (!props.match.scheduledAt) return '—';
  const d = new Date(props.match.scheduledAt);
  return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
});

const dateLabel = computed(() => {
  if (!props.match.scheduledAt) return null;
  const d = new Date(props.match.scheduledAt);
  return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
});
</script>

<template>
  <div class="min-w-[260px] rounded-xl bg-white ring-1 ring-slate-200 p-4 hover:ring-brand-300 transition">
    <div class="flex items-center justify-between mb-3">
      <span class="text-display text-2xl text-brand-700 leading-none">
        {{ match.court?.name || '—' }}
      </span>
      <div class="text-right">
        <div class="text-xs text-slate-400">{{ dateLabel }}</div>
        <div class="font-mono text-sm font-semibold text-slate-900">{{ timeLabel }}</div>
      </div>
    </div>
    <div class="text-xs uppercase tracking-widest text-slate-400 mb-1.5">
      {{ match.categoryName || match.categoryType }} · {{ match.stage }}
    </div>
    <div class="font-semibold text-slate-900 truncate">{{ match.teamA?.displayName || 'TBD' }}</div>
    <div class="text-xs text-slate-400 my-0.5">vs</div>
    <div class="font-semibold text-slate-900 truncate">{{ match.teamB?.displayName || 'TBD' }}</div>
  </div>
</template>
