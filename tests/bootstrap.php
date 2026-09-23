<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

// Some tests boot the kernel with debug off (like production, e.g. to render the branded error
// pages). Such kernels never check whether the compiled container is stale, so start every run
// with a fresh test cache.
(new Filesystem())->remove(dirname(__DIR__).'/var/cache/test');

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
