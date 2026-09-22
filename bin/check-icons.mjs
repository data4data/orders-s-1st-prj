#!/usr/bin/env node
// Every semantic icon in assets/shared/icons.json must be importable in both frontends
// (assets/bootstrap/lib/icons.js and assets/vue/shared/icons.js), otherwise it would fail at runtime.
import { readFileSync } from 'node:fs';

const map = JSON.parse(readFileSync('assets/shared/icons.json', 'utf8'));
delete map._comment;
const pascal = (name) => name.split('-').map((part) => part[0].toUpperCase() + part.slice(1)).join('');
const files = ['assets/bootstrap/lib/icons.js', 'assets/vue/shared/icons.js'];

let failures = 0;
for (const file of files) {
    const source = readFileSync(file, 'utf8');
    for (const [semantic, lucide] of Object.entries(map)) {
        if (!new RegExp(`'${lucide}':\\s*${pascal(lucide)}\\b`).test(source)) {
            console.error(`FAIL ${file}: icon "${semantic}" (lucide "${lucide}") is not mapped`);
            failures++;
        }
    }
}
if (failures) process.exit(1);
console.log(`OK   ${Object.keys(map).length} icons mapped in both frontends.`);
