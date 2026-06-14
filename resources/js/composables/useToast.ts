import { ref } from 'vue';

export type ToastKind = 'info' | 'success' | 'error' | 'warn';

export interface Toast {
  id: number;
  kind: ToastKind;
  message: string;
}

// Module-level singleton state — shared across all consumers.
const toasts = ref<Toast[]>([]);
let nextId = 1;

function push(kind: ToastKind, message: string, timeoutMs = 4000) {
  const id = nextId++;
  toasts.value.push({ id, kind, message });
  setTimeout(() => remove(id), timeoutMs);
}

function remove(id: number) {
  toasts.value = toasts.value.filter((t) => t.id !== id);
}

function info(message: string) {
  push('info', message);
}
function success(message: string) {
  push('success', message);
}
function error(message: string) {
  push('error', message, 6000);
}
function warn(message: string) {
  push('warn', message);
}

export function useToast() {
  return { toasts, push, remove, info, success, error, warn };
}

/**
 * Helper to extract a user-friendly message from an error response.
 */
export function describeError(err: unknown): string {
  const e = err as {
    response?: { data?: { message?: string | string[] } };
    message?: string;
  };
  const msg = e.response?.data?.message;
  if (Array.isArray(msg)) return msg.join(', ');
  return msg ?? e.message ?? 'Something went wrong';
}
