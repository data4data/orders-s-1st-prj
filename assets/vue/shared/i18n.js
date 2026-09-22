import { createI18n } from 'vue-i18n';
import en from '../../shared/i18n/en.json';

// Every UI text goes through translation keys; English first (decision #38). Same file as the
// jQuery side, so both frontends say exactly the same thing.
export const i18n = createI18n({
    legacy: false,
    locale: document.documentElement.lang || 'en',
    fallbackLocale: 'en',
    messages: { en },
});

export const t = i18n.global.t;
