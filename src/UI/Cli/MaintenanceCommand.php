<?php

declare(strict_types=1);

namespace App\UI\Cli;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:maintenance', description: 'Switch maintenance mode (503 page for every request) on or off')]
final class MaintenanceCommand
{
    public function __construct(
        #[Autowire('%app.maintenance_flag%')]
        private readonly string $flagFile,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('on, off or status')] string $mode = 'status'): int
    {
        match ($mode) {
            'on' => file_put_contents($this->flagFile, date(\DATE_ATOM)),
            'off' => is_file($this->flagFile) && unlink($this->flagFile),
            'status' => null,
            default => throw new \InvalidArgumentException('Use "on", "off" or "status".'),
        };

        is_file($this->flagFile)
            ? $io->warning('Maintenance mode is ON: every page answers 503.')
            : $io->success('Maintenance mode is off.');

        return Command::SUCCESS;
    }
}
