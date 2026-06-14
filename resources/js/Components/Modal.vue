<script setup lang="ts">
defineProps<{
  open: boolean;
  title: string;
}>();
const emit = defineEmits<{ (e: 'close'): void }>();
</script>

<template>
  <Teleport to="body">
    <transition name="fade">
      <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4"
        @click.self="emit('close')"
      >
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg max-h-[90vh] overflow-auto">
          <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
            <h2 class="font-semibold text-slate-900">{{ title }}</h2>
            <button
              class="text-slate-400 hover:text-slate-600 text-xl leading-none"
              @click="emit('close')"
              aria-label="Close"
            >
              ×
            </button>
          </div>
          <div class="p-5">
            <slot />
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
