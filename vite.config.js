import { defineConfig } from 'vite';
import symfonyPlugin from 'vite-plugin-symfony';
import vuePlugin from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

// Three isolated entries (architecture.md §8). Each Twig base layout loads exactly one of them:
//   bootstrap  -> templates/bootstrap/base.html.twig  (Bootstrap 5 + jQuery, no Tailwind)
//   storefront -> templates/vue/base.html.twig        (Vue 3 + PrimeVue + Tailwind, no Bootstrap)
//   admin      -> templates/vue/admin.html.twig       (Vue 3 + PrimeVue + Tailwind, no Bootstrap)
export default defineConfig({
    plugins: [vuePlugin(), tailwindcss(), symfonyPlugin()],
    build: {
        rollupOptions: {
            input: {
                bootstrap: './assets/bootstrap/app.js',
                storefront: './assets/vue/storefront.js',
                admin: './assets/vue/admin.js',
            },
        },
    },
    css: {
        preprocessorOptions: {
            scss: {
                // Bootstrap 5.3 still uses Sass features that newer Sass versions deprecate.
                quietDeps: true,
                silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'if-function'],
            },
        },
    },
    server: {
        // 5173 is used by another project on this machine.
        port: 5174,
        strictPort: true,
        cors: true,
    },
});
