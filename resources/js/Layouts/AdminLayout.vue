<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

interface AuthUser {
  id: string | number;
  username: string;
  full_name?: string | null;
  fullName?: string | null;
  role: string;
}

type NavItem = {
  label: string;
  routeName: string;
  // Route name prefix used to decide the active highlight.
  match: string;
  icon: string;
};

const page = usePage<{ auth: { user: AuthUser | null }; flash?: { success?: string | null } }>();
const user = computed(() => page.props.auth?.user ?? null);
const userLabel = computed(
  () => user.value?.full_name || user.value?.fullName || user.value?.username || '',
);
const flashSuccess = computed(() => page.props.flash?.success ?? null);

const navOpen = ref(false);

// Single source of truth — the parallel phase adds pages, not nav wiring.
const nav: NavItem[] = [
  { label: 'Dashboard', routeName: 'admin.dashboard', match: 'admin.dashboard', icon: '📊' },
  { label: 'Tournaments', routeName: 'admin.tournaments.index', match: 'admin.tournaments.', icon: '🏆' },
  { label: 'Courts', routeName: 'admin.courts.index', match: 'admin.courts.', icon: '🏟️' },
  { label: 'Categories', routeName: 'admin.categories.index', match: 'admin.categories.', icon: '🗂️' },
  { label: 'Players', routeName: 'admin.players.index', match: 'admin.players.', icon: '🧑' },
  { label: 'Teams', routeName: 'admin.teams.index', match: 'admin.teams.', icon: '👥' },
  { label: 'Matches', routeName: 'admin.matches.index', match: 'admin.matches.', icon: '🆚' },
  { label: 'Groups', routeName: 'admin.groups.index', match: 'admin.groups.', icon: '🔢' },
  { label: 'Standings', routeName: 'admin.standings.index', match: 'admin.standings.', icon: '📈' },
  { label: 'Bracket', routeName: 'admin.bracket.index', match: 'admin.bracket.', icon: '🪜' },
  { label: 'Users', routeName: 'admin.users.index', match: 'admin.users.', icon: '🔐' },
];

const currentRoute = computed<string>(() => {
  try {
    return (route().current() as string | undefined) ?? '';
  } catch {
    return '';
  }
});

function isActive(item: NavItem): boolean {
  return currentRoute.value === item.match || currentRoute.value.startsWith(item.match);
}

function logout() {
  router.post(route('logout'));
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 lg:flex">
    <!-- Sidebar (desktop) -->
    <aside class="hidden lg:flex lg:flex-col lg:w-60 lg:shrink-0 bg-white border-r border-slate-200">
      <div class="h-16 flex items-center px-5 border-b border-slate-200">
        <Link :href="route('admin.dashboard')" class="flex items-center gap-2 font-semibold text-slate-900">
          <span class="text-xl">🏸</span>
          <span class="text-sm">Tournment <span class="text-brand-600">Admin</span></span>
        </Link>
      </div>
      <nav class="flex-1 overflow-y-auto p-3 space-y-1">
        <Link
          v-for="item in nav"
          :key="item.routeName"
          :href="route(item.routeName)"
          class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition"
          :class="
            isActive(item)
              ? 'bg-brand-50 text-brand-700'
              : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
          "
        >
          <span class="text-base leading-none">{{ item.icon }}</span>
          <span>{{ item.label }}</span>
        </Link>
      </nav>
      <div class="p-3 border-t border-slate-200">
        <div class="px-3 py-1 text-xs text-slate-400 truncate">{{ userLabel }}</div>
        <button class="btn-secondary w-full text-sm" @click="logout">Logout</button>
      </div>
    </aside>

    <div class="flex-1 min-w-0 flex flex-col">
      <!-- Top bar -->
      <header class="bg-white border-b border-slate-200 sticky top-0 z-20">
        <div class="px-4 sm:px-6 h-14 sm:h-16 flex items-center justify-between gap-3">
          <div class="flex items-center gap-3 min-w-0">
            <button
              class="lg:hidden inline-flex items-center justify-center w-9 h-9 rounded-lg ring-1 ring-slate-300 text-slate-600"
              aria-label="Toggle navigation"
              @click="navOpen = !navOpen"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="6" x2="21" y2="6" />
                <line x1="3" y1="12" x2="21" y2="12" />
                <line x1="3" y1="18" x2="21" y2="18" />
              </svg>
            </button>
            <Link
              :href="route('admin.dashboard')"
              class="lg:hidden flex items-center gap-2 font-semibold text-slate-900"
            >
              <span class="text-lg">🏸</span>
              <span class="text-sm">Tournment <span class="text-brand-600">Admin</span></span>
            </Link>
          </div>
          <div class="flex items-center gap-2 sm:gap-3 shrink-0">
            <span class="text-sm text-slate-600 hidden sm:inline truncate max-w-[12rem]">{{ userLabel }}</span>
            <button class="btn-secondary text-sm" @click="logout">Logout</button>
          </div>
        </div>

        <!-- Mobile collapsible nav -->
        <nav v-if="navOpen" class="lg:hidden border-t border-slate-200 px-3 py-2 space-y-1 bg-white">
          <Link
            v-for="item in nav"
            :key="item.routeName"
            :href="route(item.routeName)"
            class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition"
            :class="
              isActive(item)
                ? 'bg-brand-50 text-brand-700'
                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
            "
            @click="navOpen = false"
          >
            <span class="text-base leading-none">{{ item.icon }}</span>
            <span>{{ item.label }}</span>
          </Link>
        </nav>
      </header>

      <!-- Flash -->
      <div v-if="flashSuccess" class="px-4 sm:px-6 pt-4">
        <div class="rounded-lg bg-live-500/10 text-live-600 ring-1 ring-live-500/30 px-4 py-2 text-sm font-medium">
          {{ flashSuccess }}
        </div>
      </div>

      <main class="flex-1 px-4 sm:px-6 py-4 sm:py-6 max-w-6xl w-full mx-auto">
        <slot />
      </main>
    </div>
  </div>
</template>
