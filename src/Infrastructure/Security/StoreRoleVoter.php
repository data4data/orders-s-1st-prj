<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Tenancy\Port\StoreMembershipRepositoryInterface;
use App\Application\Tenancy\TenantContextInterface;
use App\Entity\StaffUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Grants ROLE_STORE_STAFF / _MANAGER / _OWNER according to the staff member's role in the
 * store selected in the admin. Super-admins get all of them.
 *
 * @extends Voter<string, mixed>
 */
final class StoreRoleVoter extends Voter
{
    private const ATTRIBUTES = ['ROLE_STORE_STAFF', 'ROLE_STORE_MANAGER', 'ROLE_STORE_OWNER'];

    public function __construct(
        private readonly TenantContextInterface $tenantContext,
        private readonly StoreMembershipRepositoryInterface $memberships,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, self::ATTRIBUTES, true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof StaffUser) {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }

        $store = $this->tenantContext->getStore();
        $membership = null !== $store ? $this->memberships->findOne($user, $store) : null;

        return null !== $membership && \in_array($attribute, $membership->getRole()->securityRoles(), true);
    }
}
