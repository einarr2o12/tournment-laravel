import { createI18n } from 'vue-i18n';
import en from './locales/en';
import my from './locales/my';

export type AppLocale = 'en' | 'my';
export const LOCALE_LABELS: Record<AppLocale, string> = {
  en: 'English',
  my: 'မြန်မာ',
};
export const LOCALE_KEY = 'tournment.locale';

function pickInitialLocale(): AppLocale {
  const stored = (typeof window !== 'undefined' && localStorage.getItem(LOCALE_KEY)) || '';
  if (stored === 'en' || stored === 'my') return stored;
  if (typeof navigator !== 'undefined' && navigator.language?.toLowerCase().startsWith('my')) {
    return 'my';
  }
  return 'en';
}

export const i18n = createI18n({
  legacy: false,
  locale: pickInitialLocale(),
  fallbackLocale: 'en',
  messages: { en, my },
});

export function setLocale(loc: AppLocale) {
  i18n.global.locale.value = loc;
  if (typeof window !== 'undefined') localStorage.setItem(LOCALE_KEY, loc);
  if (typeof document !== 'undefined') document.documentElement.lang = loc;
}
