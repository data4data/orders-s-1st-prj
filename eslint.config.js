import js from '@eslint/js';
import pluginVue from 'eslint-plugin-vue';
import globals from 'globals';

export default [
    { ignores: ['public/**', 'vendor/**', 'node_modules/**', 'var/**'] },
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
    {
        files: ['bin/**/*.mjs', '*.config.js'],
        languageOptions: { globals: { ...globals.node } },
    },
];
