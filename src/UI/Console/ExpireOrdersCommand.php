<?php

declare(strict_types=1);

namespace App\UI\Console;

use App\Application\Bus\CommandBusInterface;
use App\Application\Ordering\ExpireUnpaidOrders;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The scheduled expiry, on demand: bin/console app:orders:expire --minutes=60.
 */
#[AsCommand(name: 'app:orders:expire', description: 'Cancel orders that are still awaiting payment after the time limit')]
final readonly class ExpireOrdersCommand
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function __invoke(SymfonyStyle $io, #[Option(description: 'Minutes an order may wait for payment')] int $minutes = ExpireUnpaidOrders::MINUTES): int
    {
        /** @var int $cancelled */
        $cancelled = $this->commandBus->dispatch(new ExpireUnpaidOrders($minutes));
        $io->success(sprintf('%d order(s) cancelled.', $cancelled));

        return 0;
    }
}
