<script setup lang="ts">
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { route } from 'ziggy-js';
import LanguagePicker from '../Components/LanguagePicker.vue';

interface AuthUser {
  id: number;
  username: string;
  full_name?: string | null;
  fullName?: string | null;
  role: string;
}

const props = defineProps<{
  courtName?: string | null;
  backHref?: string | null;
  backLabel?: string | null;
}>();

const { t } = useI18n();
const page = usePage<{ auth: { user: AuthUser | null } }>();
const user = computed(() => page.props.auth?.user ?? null);
const userLabel = computed(
  () => user.value?.full_name || user.value?.fullName || user.value?.username || '',
);

const resolvedBackHref = computed(() => props.backHref ?? route('referee.dashboard'));
const resolvedBackLabel = computed(() => props.backLabel ?? t('common.back'));

function logout() {
  router.post(route('logout'));
}
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <header class="bg-white border-b border-slate-200">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 h-14 sm:h-16 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
          <Link
            :href="resolvedBackHref"
            class="inline-flex items-center gap-1 text-sm font-medium text-slate-600 hover:text-brand-700"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="15 18 9 12 15 6" />
            </svg>
            <span class="hidden sm:inline">{{ resolvedBackLabel }}</span>
          </Link>
          <div v-if="courtName" class="min-w-0">
            <div class="text-xs uppercase tracking-wider text-slate-400">
              {{ t('referee.court') }}
            </div>
            <div class="text-base sm:text-lg font-semibold text-slate-900 truncate">
              {{ courtName }}
            </div>
          </div>
          <Link
            v-else
            :href="route('public.index')"
            class="text-lg font-semibold text-brand-700"
          >
            🏸 {{ t('common.appName') }}
          </Link>
        </div>
        <div class="flex items-center gap-2 sm:gap-3 shrink-0">
          <LanguagePicker />
          <span class="text-sm text-slate-600 hidden sm:inline truncate max-w-[10rem]">
            {{ userLabel }}
          </span>
          <button class="btn-secondary text-sm" @click="logout">
            {{ t('common.logout') }}
          </button>
        </div>
      </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 sm:px-6 py-4 sm:py-6">
      <slot />
    </main>
  </div>
</template>
