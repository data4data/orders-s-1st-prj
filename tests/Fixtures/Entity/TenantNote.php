<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\Entity;

use App\Entity\Contract\TenantAwareInterface;
use App\Entity\Contract\TenantAwareTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Test-only tenant entity (mapped in the test environment only) used to prove store isolation
 * before the real tenant tables (products, orders…) exist.
 */
#[ORM\Entity]
#[ORM\Table(name: 'test_tenant_note')]
class TenantNote implements TenantAwareInterface
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 100)]
        private string $text,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getText(): string
    {
        return $this->text;
    }
}
