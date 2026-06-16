<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const messages = {
  en: {
    title: 'Tournment',
    eyebrow: 'Referee console',
    headline: 'Step on court',
    subtitle: 'Sign in to start scoring',
    username: 'Username',
    usernamePh: 'ref1',
    password: 'Password',
    passwordPh: '••••••••',
    signIn: 'Enter scoring',
    signingIn: 'Signing in…',
    backPublic: 'Back to public view',
    adminHint: 'Admin? Go to',
    adminLink: '/admin',
    failed: 'Wrong username or password',
    languageLabel: 'Language',
  },
  my: {
    title: 'Tournment',
    eyebrow: 'ဒိုင်လူကြီး console',
    headline: 'ကွင်းထဲဝင်ရန်',
    subtitle: 'ရမှတ်စစ်ဖို့ ဝင်ပါ',
    username: 'အသုံးပြုသူ',
    usernamePh: 'ref1',
    password: 'စကားဝှက်',
    passwordPh: '••••••••',
    signIn: 'ဝင်မယ်',
    signingIn: 'ဝင်နေသည်…',
    backPublic: 'public view ပြန်သွား',
    adminHint: 'Admin လား?',
    adminLink: '/admin',
    failed: 'အသုံးပြုသူ သို့မဟုတ် စကားဝှက် မှားနေသည်',
    languageLabel: 'ဘာသာစကား',
  },
} as const;

type Locale = keyof typeof messages;

function readInitialLocale(): Locale {
  if (typeof window === 'undefined') return 'en';
  const stored = window.localStorage.getItem('locale');
  if (stored === 'en' || stored === 'my') return stored;
  return 'en';
}

const locale = ref<Locale>(readInitialLocale());
const t = computed(() => messages[locale.value]);

const showPassword = ref(false);

const form = useForm({
  username: '',
  password: '',
});

const genericError = computed<string | null>(() => {
  const hasFieldError = !!(form.errors.username || form.errors.password);
  const anyError = Object.keys(form.errors).length > 0;
  return anyError && !hasFieldError ? t.value.failed : null;
});

function submit() {
  form
    .transform((data) => ({
      ...data,
      username: data.username.trim(),
    }))
    .post(route('login'), {
      onFinish: () => form.reset('password'),
    });
}
</script>

<template>
  <Head :title="t.title" />

  <div class="min-h-screen gradient-hero text-white flex flex-col">
    <!-- TOP BAR -->
    <header class="px-4 sm:px-6 pt-4 sm:pt-6 flex items-center justify-between">
      <Link :href="route('public.index')" class="text-lg font-bold text-white/90 hover:text-white">
        🏸 {{ t.title }}
      </Link>
    </header>

    <!-- CENTER -->
    <main class="flex-1 flex items-center justify-center px-4 py-8">
      <div class="w-full max-w-md">
        <!-- HEADING -->
        <div class="text-center mb-6 sm:mb-8">
          <div
            class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-live-500/15 ring-1 ring-live-500/30 text-live-400 text-xs font-semibold uppercase tracking-wider mb-4"
          >
            <span class="h-1.5 w-1.5 rounded-full bg-live-400 animate-pulse"></span>
            {{ t.eyebrow }}
          </div>
          <h1 class="text-display text-5xl sm:text-6xl tracking-tight leading-[0.9]">
            {{ t.headline }}
          </h1>
          <p class="mt-3 text-slate-300 text-base">{{ t.subtitle }}</p>
        </div>

        <!-- CARD -->
        <form
          class="rounded-2xl bg-white/95 backdrop-blur-sm ring-1 ring-white/20 shadow-2xl p-6 sm:p-7 text-slate-900"
          @submit.prevent="submit"
        >
          <!-- USERNAME -->
          <div class="mb-4">
            <label class="label" for="username">{{ t.username }}</label>
            <div class="relative">
              <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                  <path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-4.418 0-8 2.91-8 6.5V22h16v-1.5c0-3.59-3.582-6.5-8-6.5Z" />
                </svg>
              </span>
              <input
                id="username"
                v-model="form.username"
                class="input pl-10 h-12 text-base"
                :placeholder="t.usernamePh"
                autocomplete="username"
                autofocus
                required
                minlength="3"
                :disabled="form.processing"
              />
            </div>
            <p v-if="form.errors.username" class="mt-1.5 text-sm text-red-600">
              {{ form.errors.username }}
            </p>
          </div>

          <!-- PASSWORD -->
          <div class="mb-5">
            <label class="label" for="password">{{ t.password }}</label>
            <div class="relative">
              <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                  <path d="M12 1a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V11a2 2 0 0 0-2-2h-1V6a5 5 0 0 0-5-5Zm-3 8V6a3 3 0 1 1 6 0v3H9Z" />
                </svg>
              </span>
              <input
                id="password"
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                class="input pl-10 pr-12 h-12 text-base"
                :placeholder="t.passwordPh"
                autocomplete="current-password"
                required
                minlength="6"
                :disabled="form.processing"
              />
              <button
                type="button"
                class="absolute inset-y-0 right-2 my-1 px-2 rounded-md text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition"
                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                @click="showPassword = !showPassword"
              >
                <svg v-if="showPassword" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                  <path d="M2.46 4.46 4.46 2.46 21.54 19.54l-2 2-3.2-3.2A11.3 11.3 0 0 1 12 19c-6 0-10-7-10-7a18 18 0 0 1 4.62-5.18L2.46 4.46Zm6.6 6.6A3 3 0 0 0 12 15a3 3 0 0 0 1.94-.71l-3.88-3.88c-.16.2-.29.4-.4.66ZM12 5c6 0 10 7 10 7a18 18 0 0 1-3.3 4.06l-2.85-2.85A5 5 0 0 0 12 7c-.36 0-.71.04-1.05.1l-2.32-2.3A11.4 11.4 0 0 1 12 5Z" />
                </svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                  <path d="M12 5c-6 0-10 7-10 7s4 7 10 7 10-7 10-7-4-7-10-7Zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8Zm0-2a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" />
                </svg>
              </button>
            </div>
            <p v-if="form.errors.password" class="mt-1.5 text-sm text-red-600">
              {{ form.errors.password }}
            </p>
          </div>

          <!-- ERROR -->
          <div
            v-if="genericError"
            class="mb-4 rounded-lg bg-red-50 border border-red-200 px-3 py-2.5 text-sm text-red-700 flex items-start gap-2"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5 shrink-0 mt-0.5">
              <path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 15h-2v-2h2v2Zm0-4h-2V7h2v6Z" />
            </svg>
            <span>{{ genericError }}</span>
          </div>

          <!-- SUBMIT -->
          <button
            type="submit"
            class="w-full h-12 inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 text-white text-base font-semibold shadow-lg shadow-brand-900/30 hover:bg-brand-700 active:bg-brand-800 transition disabled:opacity-60 disabled:cursor-not-allowed"
            :disabled="form.processing"
          >
            <svg
              v-if="form.processing"
              xmlns="http://www.w3.org/2000/svg"
              class="h-5 w-5 animate-spin"
              viewBox="0 0 24 24"
              fill="none"
            >
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.25" stroke-width="4" />
              <path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="4" stroke-linecap="round" />
            </svg>
            <span>{{ form.processing ? t.signingIn : t.signIn }}</span>
            <svg v-if="!form.processing" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
              <path d="M5 12h12.17l-5.59-5.59L13 5l8 7-8 7-1.42-1.41L17.17 13H5v-1Z" />
            </svg>
          </button>

          <!-- FOOT -->
          <div class="mt-5 flex items-center justify-between text-xs">
            <Link href="/" class="text-slate-500 hover:text-slate-700 inline-flex items-center gap-1">
              <span aria-hidden="true">←</span> {{ t.backPublic }}
            </Link>
            <span class="text-slate-400">
              {{ t.adminHint }}
              <a :href="t.adminLink" class="font-semibold text-brand-600 hover:text-brand-700">{{ t.adminLink }}</a>
            </span>
          </div>
        </form>
      </div>
    </main>

    <footer class="px-4 pb-5 text-center text-xs text-white/40">
      Tournament Service · BWF rules
    </footer>
  </div>
</template>
