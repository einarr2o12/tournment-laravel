<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
</script>

<template>
  <Head title="Not found" />
  <div class="min-h-screen flex flex-col items-center justify-center bg-slate-50 px-4">
    <div class="text-center">
      <div class="text-display text-8xl sm:text-9xl text-slate-300 leading-none">404</div>
      <h1 class="text-display text-3xl sm:text-4xl text-slate-900 mt-4">Not found</h1>
      <p class="mt-3 text-slate-500">The page you're looking for doesn't exist.</p>
      <Link :href="route('public.index')" class="btn-primary mt-6 inline-flex">
        Back to tournaments
      </Link>
    </div>
  </div>
</template>
