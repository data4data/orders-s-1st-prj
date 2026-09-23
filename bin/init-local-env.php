#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Writes this machine's APP_SECRET into .env.local, which git ignores. APP_SECRET signs CSRF
 * tokens, the signed payment page URLs and password reset links, so every install has its own
 * and none of them belong in the repository.
 */
$file = \dirname(__DIR__).'/.env.local';
if (is_file($file) && str_contains((string) file_get_contents($file), 'APP_SECRET=')) {
    echo ".env.local already has an APP_SECRET.\n";

    exit(0);
}

file_put_contents($file, sprintf(
    "# Settings for this machine only (git ignores this file).\n# APP_SECRET signs CSRF tokens, signed payment URLs and password reset links.\nAPP_SECRET=%s\n",
    bin2hex(random_bytes(16)),
), \FILE_APPEND);

echo "Wrote a new APP_SECRET to .env.local.\n";
