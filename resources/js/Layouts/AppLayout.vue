<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { route } from 'ziggy-js';

const { t } = useI18n();
</script>

<template>
  <div class="min-h-screen gradient-hero text-white flex flex-col">
    <header class="px-4 sm:px-6 h-14 sm:h-16 flex items-center justify-between">
      <Link :href="route('public.index')" class="text-lg sm:text-xl font-bold text-white">
        🏸 {{ t('common.appName') }}
      </Link>
    </header>

    <main class="flex-1 flex items-center justify-center px-4 sm:px-6 py-8">
      <div class="w-full max-w-md">
        <slot />
      </div>
    </main>
  </div>
</template>
