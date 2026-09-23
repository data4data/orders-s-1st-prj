<?php

declare(strict_types=1);

// Lets PHPStan's Doctrine extension read the entity metadata. The test environment is used so
// the test-only entities in tests/Fixtures/Entity are known as well.

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__.'/../vendor/autoload.php';

(new Dotenv())->bootEnv(__DIR__.'/../.env');

$kernel = new Kernel('test', true);
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();
