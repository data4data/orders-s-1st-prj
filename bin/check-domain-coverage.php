#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * Fails unless every executable line in src/Domain is covered (plan: 100% Domain coverage).
 * Usage: vendor/bin/phpunit --testsuite unit --coverage-clover var/coverage/clover.xml
 *        php bin/check-domain-coverage.php var/coverage/clover.xml
 */

$file = $argv[1] ?? 'var/coverage/clover.xml';
if (!is_file($file)) {
    fwrite(\STDERR, "Coverage report {$file} not found.\n");
    exit(1);
}

$xml = simplexml_load_file($file);
if (false === $xml) {
    fwrite(\STDERR, "Cannot read {$file}.\n");
    exit(1);
}

$total = 0;
$covered = 0;
$uncovered = [];
foreach ($xml->xpath('//file') ?: [] as $node) {
    $relativePath = strstr((string) $node['name'], 'src/Domain/');
    if (false === $relativePath) {
        continue;
    }
    foreach ($node->line as $line) {
        if ('stmt' !== (string) $line['type']) {
            continue;
        }
        ++$total;
        if ((int) $line['count'] > 0) {
            ++$covered;
        } else {
            $uncovered[] = $relativePath.':'.$line['num'];
        }
    }
}

if (0 === $total) {
    fwrite(\STDERR, "No src/Domain lines found in {$file}.\n");
    exit(1);
}

printf("Domain line coverage: %d / %d (%.2f%%)\n", $covered, $total, 100 * $covered / $total);
if ([] !== $uncovered) {
    fwrite(\STDERR, "Uncovered lines:\n  ".implode("\n  ", $uncovered)."\n");
    exit(1);
}
