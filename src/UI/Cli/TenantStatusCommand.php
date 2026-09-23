<?php

declare(strict_types=1);

namespace App\UI\Cli;

use App\Application\Bus\QueryBusInterface;
use App\Application\Tenancy\Query\GetTenantStatus;
use App\Application\Tenancy\View\TenantStatusView;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shows which store a command runs for. Example: bin/console app:tenant:status --store=myoils-auto.
 */
#[AsCommand(name: 'app:tenant:status', description: 'Show which store console commands run for (use --store=<code>)')]
final class TenantStatusCommand
{
    public function __construct(private readonly QueryBusInterface $queryBus)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        /** @var TenantStatusView $status */
        $status = $this->queryBus->ask(new GetTenantStatus());

        if (null === $status->store) {
            $io->warning('No store is active. Tenant data is not visible. Add --store=<code> to run for a store.');

            return Command::SUCCESS;
        }

        $io->definitionList(
            ['Store' => $status->store->name],
            ['Code' => $status->store->code],
            ['Country' => $status->store->countryCode],
            ['Currency' => $status->store->currencyCode],
        );

        return Command::SUCCESS;
    }
}
