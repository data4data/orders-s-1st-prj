#!/usr/bin/env node
// Fails when Bootstrap styles leak into the Vue entries or Tailwind styles leak into the
// Bootstrap entry (architecture.md §8). Run after `vite build`.
import { readFileSync } from 'node:fs';
import { join } from 'node:path';

const buildDir = 'public/build';
const { entryPoints } = JSON.parse(readFileSync(join(buildDir, '.vite/entrypoints.json'), 'utf8'));

// Markers that only appear in the other framework's CSS.
const rules = {
    bootstrap: {
        forbidden: 'Tailwind',
        patterns: [/--tw-[a-z-]+\s*:/, /tailwindcss v\d/i, /@layer\s+theme\s*[,{]/],
    },
    vue: {
        forbidden: 'Bootstrap',
        patterns: [/--bs-[a-z-]+\s*:/, /Bootstrap\s+v\d/i, /\.btn[\s{,.:]/, /\.container[\s{,]/],
    },
};

const entryRules = { bootstrap: rules.bootstrap, storefront: rules.vue, admin: rules.vue };
let failures = 0;

for (const [entry, rule] of Object.entries(entryRules)) {
    const files = entryPoints[entry]?.css ?? [];
    if (!entryPoints[entry]) {
        console.error(`FAIL entry "${entry}" is missing from the build`);
        failures++;
        continue;
    }
    for (const file of files) {
        const css = readFileSync(join('public', file), 'utf8');
        const hit = rule.patterns.find((pattern) => pattern.test(css));
        if (hit) {
            console.error(`FAIL ${entry}: ${file} contains ${rule.forbidden} CSS (matched ${hit})`);
            failures++;
        } else {
            console.log(`OK   ${entry}: ${file} is free of ${rule.forbidden} CSS`);
        }
    }
}

process.exit(failures ? 1 : 0);
