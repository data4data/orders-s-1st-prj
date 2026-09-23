import js from '@eslint/js';
import pluginVue from 'eslint-plugin-vue';
import globals from 'globals';

export default [
    { ignores: ['public/**', 'vendor/**', 'node_modules/**', 'var/**', 'test-results/**', 'playwright-report/**'] },
    js.configs.recommended,
    ...pluginVue.configs['flat/recommended'],
    {
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: { ...globals.browser },
        },
        rules: {
            'vue/html-indent': ['error', 4],
            'vue/singleline-html-element-content-newline': 'off',
            // Line breaking of attributes is formatting (Prettier's job), not correctness.
            'vue/max-attributes-per-line': 'off',
        },
    },
    // Style isolation (architecture.md §8): the Bootstrap stack must not pull in Vue, PrimeVue or Tailwind…
    {
        files: ['assets/bootstrap/**/*.{js,mjs}'],
        rules: {
            'no-restricted-imports': ['error', {
                patterns: [
                    { group: ['**/vue/**', 'vue', 'vue-*', 'primevue', 'primevue/*', '@primeuix/*', 'tailwindcss', 'tailwindcss-*', 'lucide-vue-next'],
                        message: 'Bootstrap pages must stay free of Vue, PrimeVue and Tailwind.' },
                ],
            }],
        },
    },
    // …and the Vue stack must not pull in Bootstrap or jQuery.
    {
        files: ['assets/vue/**/*.{js,mjs,vue}'],
        rules: {
            'no-restricted-imports': ['error', {
                patterns: [
                    { group: ['**/bootstrap/**', 'bootstrap', 'bootstrap/*', 'jquery'],
                        message: 'Vue pages must stay free of Bootstrap and jQuery.' },
                ],
            }],
        },
    },
    // Every UI text goes through translation keys (decision #38). The UI kit demo page is exempt.
    {
        files: ['assets/vue/**/*.vue'],
        ignores: ['assets/vue/pages/UiKit.vue'],
        rules: {
            'vue/no-bare-strings-in-template': ['error', { allowlist: ['·', '*', '&copy;', '©', '(', ')', '-', ':', '.', ','] }],
        },
    },
    // assets/shared is used by both stacks, so it may depend on neither.
    {
        files: ['assets/shared/**/*.js'],
        rules: {
            'no-restricted-imports': ['error', {
                patterns: [
                    { group: ['**/vue/**', '**/bootstrap/**', 'vue', 'vue-*', 'primevue', 'primevue/*', 'bootstrap', 'jquery', 'lucide', 'lucide-vue-next'],
                        message: 'assets/shared must stay framework-free (used by both frontends).' },
                ],
            }],
        },
    },
    {
        files: ['bin/**/*.mjs', '*.config.js', 'tests/e2e/**/*.js'],
        languageOptions: { globals: { ...globals.node } },
    },
];
