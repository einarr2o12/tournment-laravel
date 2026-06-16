<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { route } from 'ziggy-js';
import PublicLayout from '../../Layouts/PublicLayout.vue';

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

const { t } = useI18n();

// ---- date range (Asia/Yangon, matches MatchCard's tz) ----
const TZ = 'Asia/Yangon';
function fmtDay(iso: string | null | undefined): string | null {
  if (!iso) return null;
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return null;
  return new Intl.DateTimeFormat('en-GB', {
    timeZone: TZ,
    day: '2-digit',
    month: 'short',
  })
    .format(d)
    .toUpperCase();
}
function dateRange(
  start: string | null | undefined,
  end: string | null | undefined,
): string {
  const s = fmtDay(start);
  if (!s) return '—';
  const e = fmtDay(end);
  return !e || e === s ? s : `${s} – ${e}`;
}

function formatLabel(format: string): string {
  const key = `formats.${format}`;
  const tr = t(key);
  return tr === key ? format : tr;
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

type Kind = 'live' | 'upcoming' | 'past';
const sections = computed<
  { kind: Kind; label: string; items: TournamentSummary[] }[]
>(() => [
  { kind: 'live', label: 'Live now', items: live.value },
  { kind: 'upcoming', label: 'Upcoming', items: upcoming.value },
  { kind: 'past', label: 'Past', items: past.value },
]);
</script>

<template>
  <Head :title="t('public.title')" />

  <PublicLayout>
    <!-- slim dark hero title/meta (under the shell's logo bar) -->
    <template #hero>
      <div class="pt-4">
        <p class="bwf-label">{{ t('brand.tagline') }}</p>
        <h1
          class="mt-1 text-3xl font-bold tracking-tight text-[var(--color-bwf-text)] sm:text-4xl"
        >
          {{ t('public.title') }}
        </h1>
        <p class="mt-1.5 text-sm text-[var(--color-bwf-text-2)]">
          {{ t('public.subtitle') }}
        </p>
      </div>
    </template>

    <!-- EMPTY -->
    <div
      v-if="tournaments.length === 0"
      class="bwf-surface flex flex-col items-center justify-center px-6 py-20 text-center"
    >
      <div class="mb-3 text-5xl">🏸</div>
      <p class="text-base text-[var(--color-bwf-text-2)]">
        {{ t('public.noTournaments') }}
      </p>
    </div>

    <!-- SECTIONS -->
    <div v-else class="space-y-10">
      <section
        v-for="sec in sections"
        v-show="sec.items.length"
        :key="sec.kind"
      >
        <!-- section header -->
        <div class="mb-3 flex items-center gap-2.5">
          <span
            class="h-4 w-1 rounded-full"
            :class="
              sec.kind === 'live'
                ? 'bg-[var(--color-bwf-green)]'
                : sec.kind === 'upcoming'
                  ? 'bg-[var(--color-bwf-red)]'
                  : 'bg-[var(--color-bwf-hairline)]'
            "
          />
          <h2 class="bwf-label text-sm tracking-[0.16em]">{{ sec.label }}</h2>
          <span class="bwf-seed">{{ sec.items.length }}</span>
        </div>

        <!-- card grid -->
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <Link
            v-for="ti in sec.items"
            :key="ti.id"
            :href="route('public.tournament.show', { tournament: ti.id })"
            class="group bwf-surface flex flex-col gap-3 p-4 transition hover:ring-white/15 sm:p-5"
            :class="
              sec.kind === 'live'
                ? 'ring-[var(--color-bwf-green)]/20 hover:ring-[var(--color-bwf-green)]/40'
                : ''
            "
          >
            <!-- status chip row -->
            <div class="flex items-center justify-between gap-2">
              <span v-if="sec.kind === 'live'" class="bwf-chip-live">
                <span class="bwf-dot bwf-dot-live" />
                {{ t('match.status.IN_PROGRESS') }}
              </span>
              <span
                v-else-if="sec.kind === 'upcoming'"
                class="bwf-label rounded-full bg-[var(--color-bwf-raised)] px-2.5 py-0.5 text-[var(--color-bwf-text-2)] ring-1 ring-inset ring-white/5"
              >
                {{ t('statuses.SCHEDULED') }}
              </span>
              <span
                v-else
                class="bwf-label rounded-full bg-[var(--color-bwf-raised)] px-2.5 py-0.5 ring-1 ring-inset ring-white/5"
              >
                {{ t('statuses.COMPLETED') }}
              </span>
              <span
                class="text-[var(--color-bwf-muted)] transition group-hover:translate-x-0.5 group-hover:text-[var(--color-bwf-text-2)]"
                aria-hidden="true"
                >→</span
              >
            </div>

            <!-- name -->
            <h3
              class="text-lg font-bold leading-tight tracking-tight text-[var(--color-bwf-text)]"
            >
              {{ ti.name }}
            </h3>

            <!-- meta -->
            <div class="mt-auto space-y-1.5 pt-1">
              <div
                v-if="ti.venue"
                class="flex items-center gap-1.5 text-sm text-[var(--color-bwf-text-2)]"
              >
                <span class="text-[var(--color-bwf-muted)]">📍</span>
                <span class="truncate">{{ ti.venue }}</span>
              </div>
              <div
                class="flex items-center gap-1.5 text-sm text-[var(--color-bwf-text-2)]"
              >
                <span class="text-[var(--color-bwf-muted)]">🗓</span>
                <span class="bwf-score text-[var(--color-bwf-text-2)]">{{
                  dateRange(ti.startDate, ti.endDate)
                }}</span>
              </div>
            </div>

            <!-- bottom strip: format -->
            <div class="flex items-center border-t bwf-hairline pt-2.5">
              <span class="bwf-label">{{ formatLabel(ti.format) }}</span>
            </div>
          </Link>
        </div>
      </section>
    </div>
  </PublicLayout>
</template>
