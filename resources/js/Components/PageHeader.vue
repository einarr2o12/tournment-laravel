<script setup lang="ts">
defineProps<{
  title: string;
  subtitle?: string;
}>();
</script>

<template>
  <div class="border-b border-slate-200 bg-white">
    <div class="px-4 sm:px-8 py-4 sm:py-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
      <div class="min-w-0">
        <h1 class="text-xl sm:text-2xl font-bold text-slate-900 truncate">{{ title }}</h1>
        <p v-if="subtitle" class="text-sm text-slate-500 mt-0.5 line-clamp-2 sm:truncate">
          {{ subtitle }}
        </p>
      </div>
      <div class="flex items-center gap-2 shrink-0 -mx-1 overflow-x-auto pb-1 sm:pb-0 sm:mx-0">
        <slot name="actions" />
      </div>
    </div>
    <slot name="tabs" />
  </div>
</template>
