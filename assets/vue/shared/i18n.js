import { createI18n } from 'vue-i18n';
import en from '../i18n/en.json';

// Every UI text goes through translation keys; English first (decision #38).
export const i18n = createI18n({
    legacy: false,
    locale: document.documentElement.lang || 'en',
    fallbackLocale: 'en',
    messages: { en },
});
