<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

interface TournamentSummary {
  id: string;
  name: string;
  status: 'SCHEDULED' | 'IN_PROGRESS' | 'COMPLETED' | string;
  format: string;
  venue?: string | null;
  startDate?: string | null;
  endDate?: string | null;
}

const props = defineProps<{
  tournaments: TournamentSummary[];
}>();

function formatDateRange(start: string | null | undefined, end: string | null | undefined) {
  if (!start) return '—';
  const s = new Date(start);
  if (!end) return s.toLocaleDateString();
  const e = new Date(end);
  return `${s.toLocaleDateString([], { month: 'short', day: 'numeric' })} – ${e.toLocaleDateString([], { month: 'short', day: 'numeric' })}`;
}

const live = computed(() =>
  props.tournaments.filter((ti) => ti.status === 'IN_PROGRESS'),
);
const upcoming = computed(() =>
  props.tournaments.filter((ti) => ti.status === 'SCHEDULED'),
);
const past = computed(() =>
  props.tournaments.filter((ti) => ti.status === 'COMPLETED'),
);
</script>

<template>
  <Head title="Tournaments" />
  <div class="min-h-screen flex flex-col">
    <!-- HERO -->
    <header class="gradient-hero text-white">
      <div class="max-w-6xl mx-auto px-4 sm:px-6 h-14 sm:h-16 flex items-center justify-between gap-2">
        <Link :href="route('public.index')" class="text-lg sm:text-xl font-bold text-white truncate">
          🏸 Tournament Service
        </Link>
        <nav class="flex items-center gap-2">
          <Link
            :href="route('login')"
            class="btn-primary bg-white text-brand-700 hover:bg-slate-100"
          >
            Login
          </Link>
        </nav>
      </div>

      <div class="max-w-6xl mx-auto px-4 sm:px-6 py-12 sm:py-20 text-center">
        <h1 class="text-display text-6xl sm:text-8xl tracking-tight leading-[0.9]">
          Live Tournaments
        </h1>
        <p class="mt-5 text-lg sm:text-xl text-slate-300 max-w-2xl mx-auto">
          Follow scores in real time
        </p>
      </div>
    </header>

    <main class="flex-1 max-w-6xl w-full mx-auto px-4 sm:px-6 py-10 space-y-10">
      <div v-if="tournaments.length === 0" class="card text-center py-16">
        <div class="text-6xl mb-4">🏸</div>
        <p class="text-xl text-slate-600">No tournaments yet</p>
      </div>
      <template v-else>
        <!-- LIVE NOW -->
        <section v-if="live.length > 0">
          <div class="flex items-baseline gap-3 mb-4">
            <h2 class="text-display text-3xl sm:text-4xl text-slate-900 leading-none">
              Live now
            </h2>
            <span class="live-dot"></span>
          </div>
          <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <Link
              v-for="ti in live"
              :key="ti.id"
              :href="route('public.tournament.show', { tournament: ti.id })"
              class="group relative overflow-hidden rounded-2xl bg-slate-900 text-white ring-1 ring-live-500/30 shadow-xl p-6 transition hover:ring-live-500/60"
            >
              <div class="flex items-center justify-between mb-4">
                <span class="chip bg-live-500/20 text-live-400 ring-live-500/30">
                  <span class="live-dot mr-1.5"></span>
                  LIVE
                </span>
                <span class="text-display text-xl text-slate-400 leading-none">→</span>
              </div>
              <h3 class="text-display text-3xl leading-tight mb-3">{{ ti.name }}</h3>
              <div class="text-sm text-slate-300 space-y-1">
                <div v-if="ti.venue">📍 {{ ti.venue }}</div>
                <div>🗓 {{ formatDateRange(ti.startDate, ti.endDate) }}</div>
                <div class="text-slate-400">{{ ti.format }}</div>
              </div>
            </Link>
          </div>
        </section>

        <!-- UPCOMING -->
        <section v-if="upcoming.length > 0">
          <h2 class="text-display text-3xl sm:text-4xl text-slate-900 mb-4 leading-none">
            Upcoming
          </h2>
          <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <Link
              v-for="ti in upcoming"
              :key="ti.id"
              :href="route('public.tournament.show', { tournament: ti.id })"
              class="group relative overflow-hidden rounded-2xl bg-white ring-1 ring-slate-200 hover:ring-brand-400 shadow-sm p-6 transition"
            >
              <span class="chip-soon mb-3 inline-flex">{{ ti.status }}</span>
              <h3 class="text-display text-2xl text-slate-900 mb-3">{{ ti.name }}</h3>
              <div class="text-sm text-slate-600 space-y-1">
                <div v-if="ti.venue">📍 {{ ti.venue }}</div>
                <div>🗓 {{ formatDateRange(ti.startDate, ti.endDate) }}</div>
                <div class="text-slate-400">{{ ti.format }}</div>
              </div>
            </Link>
          </div>
        </section>

        <!-- PAST -->
        <section v-if="past.length > 0">
          <h2 class="text-display text-3xl sm:text-4xl text-slate-500 mb-4 leading-none">
            Past
          </h2>
          <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <Link
              v-for="ti in past"
              :key="ti.id"
              :href="route('public.tournament.show', { tournament: ti.id })"
              class="rounded-xl bg-white ring-1 ring-slate-200 p-4 hover:ring-slate-300 transition"
            >
              <h3 class="font-semibold text-slate-700">{{ ti.name }}</h3>
              <div class="text-xs text-slate-400 mt-1">
                {{ formatDateRange(ti.startDate, ti.endDate) }}
              </div>
            </Link>
          </div>
        </section>
      </template>
    </main>

    <footer class="bg-white border-t border-slate-200 py-6 text-center text-xs text-slate-400">
      Tournament Service · BWF rules
    </footer>
  </div>
</template>
