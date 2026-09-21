<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Platform table. The store's country decides which VAT rates apply.
 */
#[ORM\Entity]
#[ORM\Table(name: 'country')]
class Country
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 2, options: ['fixed' => true])]
        private string $code,
        #[ORM\Column(length: 100)]
        private string $name,
        #[ORM\Column]
        private bool $isEu = true,
    ) {
        $this->code = strtoupper($code);
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isEu(): bool
    {
        return $this->isEu;
    }
}
