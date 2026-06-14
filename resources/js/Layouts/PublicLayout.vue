<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
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

const { t } = useI18n();
const page = usePage<{ auth: { user: AuthUser | null } }>();

const user = computed(() => page.props.auth?.user ?? null);
const isAuthenticated = computed(() => !!user.value);
const isAdmin = computed(() => user.value?.role === 'ADMIN');
const isReferee = computed(() => user.value?.role === 'REFEREE');
</script>

<template>
  <div class="min-h-screen flex flex-col bg-slate-50">
    <header class="gradient-hero text-white">
      <div class="max-w-6xl mx-auto px-4 sm:px-6 h-14 sm:h-16 flex items-center justify-between gap-2">
        <Link :href="route('public.index')" class="text-lg sm:text-xl font-bold text-white truncate">
          🏸 {{ t('common.appName') }}
        </Link>
        <nav class="flex items-center gap-2">
          <LanguagePicker />
          <template v-if="isAuthenticated">
            <Link
              v-if="isAdmin"
              :href="route('admin.dashboard')"
              class="btn-secondary text-sm"
            >
              {{ t('common.adminConsole') }}
            </Link>
            <Link
              v-else-if="isReferee"
              :href="route('referee.dashboard')"
              class="btn-secondary text-sm"
            >
              {{ t('common.refereeDashboard') }}
            </Link>
          </template>
          <Link
            v-else
            :href="route('login')"
            class="btn-secondary text-sm"
          >
            {{ t('common.signIn') }}
          </Link>
        </nav>
      </div>
    </header>

    <main class="flex-1 min-w-0">
      <slot />
    </main>
  </div>
</template>
