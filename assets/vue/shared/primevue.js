import PrimeVue from 'primevue/config';
import { definePreset } from '@primeuix/themes';
import Aura from '@primeuix/themes/aura';

const SHADES = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

/**
 * Aura (decision #28) with the primary palette pointed at the store's --brand-primary-* CSS
 * variables, which the base layout prints per store (decision #43). tailwindcss-primeui reads the
 * same palette, so Tailwind's primary-* utilities follow the store colours too.
 */
const BrandPreset = definePreset(Aura, {
    semantic: {
        primary: Object.fromEntries(SHADES.map((shade) => [shade, `var(--brand-primary-${shade})`])),
        colorScheme: {
            light: {
                primary: {
                    color: 'var(--brand-primary)',
                    contrastColor: 'var(--brand-on-primary)',
                    hoverColor: 'var(--brand-primary-600)',
                    activeColor: 'var(--brand-primary-700)',
                },
            },
        },
    },
});

/** @param {import('vue').App} app */
export function installPrimeVue(app) {
    app.use(PrimeVue, {
        theme: {
            preset: BrandPreset,
            options: {
                darkModeSelector: false, // light theme only (decision #44)
                // Tailwind utilities come after PrimeVue's styles, so utility classes can override components.
                cssLayer: { name: 'primevue', order: 'theme, base, primevue' },
            },
        },
    });
}
