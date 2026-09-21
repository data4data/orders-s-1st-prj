import PrimeVue from 'primevue/config';
import Aura from '@primeuix/themes/aura';

/**
 * Installs PrimeVue in styled mode with the Aura preset (decision #28).
 * Per-store primary colours are applied at runtime in Phase 3.
 *
 * @param {import('vue').App} app
 */
export function installPrimeVue(app) {
    app.use(PrimeVue, {
        theme: {
            preset: Aura,
            options: { darkModeSelector: false }, // light theme only (decision #44)
        },
    });
}
