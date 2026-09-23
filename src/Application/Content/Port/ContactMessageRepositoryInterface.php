<?php

declare(strict_types=1);

namespace App\Application\Content\Port;

use App\Entity\ContactMessage;

interface ContactMessageRepositoryInterface
{
    public function find(int $id): ?ContactMessage;

    /**
     * @return array{items: list<ContactMessage>, total: int, unread: int}
     */
    public function page(bool $unreadOnly, int $page, int $perPage): array;

    public function save(ContactMessage $message): void;
}
