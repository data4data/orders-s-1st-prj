<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Console;

use App\Application\Tenancy\Port\StoreRepositoryInterface;
use App\Infrastructure\Tenancy\TenantContext;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Adds a global `--store=<code>` option to every console command and scopes the command to it.
 * Without the option, commands run with no store (tenant queries return nothing).
 */
#[AsEventListener(event: ConsoleEvents::COMMAND)]
final readonly class StoreOptionListener
{
    public const OPTION = 'store';

    public function __construct(
        private StoreRepositoryInterface $stores,
        private TenantContext $tenantContext,
    ) {
    }

    public function __invoke(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        if (null === $command) {
            return;
        }

        // Registered as a global application option (like --env): Symfony rebuilds each command's
        // definition from its own options plus the application's options right before it runs.
        $application = $command->getApplication();
        if (null === $application) {
            return;
        }
        $applicationDefinition = $application->getDefinition();
        if (!$applicationDefinition->hasOption(self::OPTION)) {
            $applicationDefinition->addOption(new InputOption(self::OPTION, null, InputOption::VALUE_REQUIRED, 'Run the command for one store (store code, e.g. myoils-auto)'));
        }
        $command->mergeApplicationDefinition();

        $input = $event->getInput();
        try {
            $input->bind($command->getDefinition());
        } catch (ExceptionInterface) {
            return; // the command reports invalid input itself when it runs
        }

        $code = $input->getOption(self::OPTION);
        if (!\is_string($code) || '' === $code) {
            return;
        }

        $store = $this->stores->findByCode($code)
            ?? throw new InvalidOptionException(sprintf('Unknown store "%s". Use a store code such as "myoils-auto".', $code));

        $this->tenantContext->useStore($store);
    }
}
