<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Tenancy\StoreRole;
use Doctrine\ORM\Mapping as ORM;

/**
 * Platform table: gives a staff user a role in one store.
 */
#[ORM\Entity]
#[ORM\Table(name: 'store_membership')]
#[ORM\UniqueConstraint(name: 'uniq_membership_staff_store', columns: ['staff_user_id', 'store_id'])]
class StoreMembership
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: StaffUser::class)]
        #[ORM\JoinColumn(name: 'staff_user_id', nullable: false, onDelete: 'CASCADE')]
        private StaffUser $staffUser,
        #[ORM\ManyToOne(targetEntity: Store::class)]
        #[ORM\JoinColumn(name: 'store_id', nullable: false, onDelete: 'CASCADE')]
        private Store $store,
        #[ORM\Column(length: 16, enumType: StoreRole::class)]
        private StoreRole $role,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStaffUser(): StaffUser
    {
        return $this->staffUser;
    }

    public function getStore(): Store
    {
        return $this->store;
    }

    public function getRole(): StoreRole
    {
        return $this->role;
    }

    public function changeRole(StoreRole $role): void
    {
        $this->role = $role;
    }
}
