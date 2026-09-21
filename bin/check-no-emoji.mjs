#!/usr/bin/env node
// Fails when an emoji appears in code, templates, translations or docs (decision #54:
// icons come from Lucide only).
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, extname } from 'node:path';

const roots = ['assets', 'templates', 'translations', 'src', 'config', 'docs', 'tests'];
const extensions = new Set(['.js', '.mjs', '.vue', '.css', '.scss', '.twig', '.yaml', '.yml', '.json', '.xlf', '.php', '.html', '.md']);
// Emoji and pictographs, plus the emoji variation selector.
const emoji = /[\u{1F000}-\u{1FAFF}\u{2600}-\u{27BF}\u{2B00}-\u{2BFF}]|\u{FE0F}/u;

let failures = 0;

function walk(dir) {
    let entries;
    try {
        entries = readdirSync(dir);
    } catch {
        return;
    }
    for (const name of entries) {
        const path = join(dir, name);
        if (statSync(path).isDirectory()) {
            walk(path);
        } else if (extensions.has(extname(name))) {
            readFileSync(path, 'utf8').split('\n').forEach((line, index) => {
                if (emoji.test(line)) {
                    console.error(`${path}:${index + 1}: emoji found, use a Lucide icon instead`);
                    failures++;
                }
            });
        }
    }
}

roots.forEach(walk);
if (failures) {
    process.exit(1);
}
console.log('No emojis found.');
