<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__.'/src', __DIR__.'/tests', __DIR__.'/config'])
    ->notPath('reference.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        'declare_strict_types' => true,
        'strict_comparison' => true,
        'native_function_invocation' => false,
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder);
