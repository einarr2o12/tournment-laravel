<script setup lang="ts">
import { useToast } from '../composables/useToast';

const { toasts, remove } = useToast();

function classFor(kind: string) {
  switch (kind) {
    case 'success':
      return 'bg-emerald-600 text-white';
    case 'error':
      return 'bg-red-600 text-white';
    case 'warn':
      return 'bg-amber-500 text-white';
    default:
      return 'bg-slate-800 text-white';
  }
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed top-4 right-4 z-[100] flex flex-col gap-2 pointer-events-none max-w-sm">
      <transition-group name="toast">
        <div
          v-for="t in toasts"
          :key="t.id"
          :class="[
            'pointer-events-auto px-4 py-3 rounded-md shadow-lg text-sm flex items-start gap-3',
            classFor(t.kind),
          ]"
        >
          <span class="flex-1">{{ t.message }}</span>
          <button
            class="opacity-70 hover:opacity-100 text-lg leading-none -mt-0.5"
            @click="remove(t.id)"
            aria-label="Dismiss"
          >
            ×
          </button>
        </div>
      </transition-group>
    </div>
  </Teleport>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.2s ease;
}
.toast-enter-from {
  opacity: 0;
  transform: translateX(20px);
}
.toast-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
