<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { LOCALE_LABELS, setLocale, type AppLocale } from '../i18n';

const { locale, t } = useI18n();

const value = computed<AppLocale>({
  get: () => locale.value as AppLocale,
  set: (v) => setLocale(v),
});
</script>

<template>
  <label class="inline-flex items-center gap-2 text-sm text-slate-600">
    <span class="sr-only">{{ t('language.label') }}</span>
    <select
      v-model="value"
      class="rounded-md border border-slate-300 bg-white px-2 py-1 text-sm focus:border-brand-500 focus:outline-none"
    >
      <option v-for="(label, code) in LOCALE_LABELS" :key="code" :value="code">{{ label }}</option>
    </select>
  </label>
</template>
