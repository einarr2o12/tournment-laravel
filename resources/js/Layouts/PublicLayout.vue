<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { route } from 'ziggy-js';

interface AuthUser {
  id: number;
  username: string;
  full_name?: string | null;
  fullName?: string | null;
  role: string;
}

/**
 * BWF dark public shell (reference image 1).
 *
 *  - slim dark hero/header (logo + auth link)
 *  - optional `tabs` => a sticky tab bar under the hero. Active tab is
 *    white-on-dark; inactive is transparent/light. Tabs are model-driven:
 *    pass v-model:tab (the active `key`) and a `tabs` array; the layout emits
 *    `update:tab` when one is clicked (no navigation — pages own the state).
 *  - `#hero` slot for page-specific hero content (title, meta, back link).
 *  - default slot = page body, on the near-black page background.
 */
export interface PublicTab {
  key: string;
  label: string;
}

const props = withDefaults(
  defineProps<{
    tabs?: PublicTab[];
    tab?: string | null;
  }>(),
  { tabs: () => [], tab: null },
);

const emit = defineEmits<{ 'update:tab': [key: string] }>();

const { t } = useI18n();
const page = usePage<{ auth: { user: AuthUser | null } }>();

const user = computed(() => page.props.auth?.user ?? null);
const isAuthenticated = computed(() => !!user.value);
const isAdmin = computed(() => user.value?.role === 'ADMIN');
const isReferee = computed(() => user.value?.role === 'REFEREE');

const hasTabs = computed(() => props.tabs.length > 0);
</script>

<template>
  <div class="bwf-page flex min-h-screen flex-col text-[var(--color-bwf-text)]">
    <!-- slim dark hero/header -->
    <header class="bwf-hero border-b bwf-hairline">
      <div
        class="mx-auto flex h-14 max-w-6xl items-center justify-between gap-2 px-4 sm:px-6"
      >
        <Link
          :href="route('public.index')"
          class="flex items-center gap-2 truncate text-base font-bold tracking-tight text-[var(--color-bwf-text)] sm:text-lg"
        >
          🏸 {{ t('common.appName') }}
        </Link>
        <nav class="flex items-center gap-2">
          <template v-if="isAuthenticated">
            <Link
              v-if="isAdmin"
              :href="route('admin.dashboard')"
              class="bwf-btn"
            >
              {{ t('common.adminConsole') }}
            </Link>
            <Link
              v-else-if="isReferee"
              :href="route('referee.dashboard')"
              class="bwf-btn"
            >
              {{ t('common.refereeDashboard') }}
            </Link>
          </template>
          <Link v-else :href="route('login')" class="bwf-btn">
            {{ t('common.signIn') }}
          </Link>
        </nav>
      </div>

      <!-- page-specific hero content (title / meta / back link) -->
      <div v-if="$slots.hero" class="mx-auto max-w-6xl px-4 pb-5 sm:px-6">
        <slot name="hero" />
      </div>
    </header>

    <!-- sticky tab bar -->
    <div
      v-if="hasTabs"
      class="sticky top-0 z-20 border-b bwf-hairline bg-[var(--color-bwf-page)]/90 backdrop-blur"
    >
      <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <nav class="flex gap-1 overflow-x-auto py-2">
          <button
            v-for="tb in tabs"
            :key="tb.key"
            type="button"
            :class="props.tab === tb.key ? 'bwf-tab-active' : 'bwf-tab'"
            @click="emit('update:tab', tb.key)"
          >
            {{ tb.label }}
          </button>
        </nav>
      </div>
    </div>

    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-6 sm:px-6">
      <slot />
    </main>
  </div>
</template>
