<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Doctrine;

use App\Entity\Contract\TenantAwareInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Adds `store_id = <active store>` to every SQL query on a TenantAwareInterface entity.
 * Without an active store it adds `1 = 0`: nothing is returned (fail closed, decision #30).
 */
final class TenantFilter extends SQLFilter
{
    public const NAME = 'tenant';
    public const PARAMETER = 'store_id';

    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!$targetEntity->getReflectionClass()->implementsInterface(TenantAwareInterface::class)) {
            return '';
        }

        if (!$this->hasParameter(self::PARAMETER)) {
            return '1 = 0';
        }

        return sprintf('%s.store_id = %s', $targetTableAlias, $this->getParameter(self::PARAMETER));
    }
}
