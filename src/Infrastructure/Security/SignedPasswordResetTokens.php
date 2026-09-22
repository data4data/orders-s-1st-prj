<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Customer\PasswordResetTokensInterface;
use App\Application\Customer\Port\CustomerRepositoryInterface;
use App\Entity\Customer;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

/**
 * Stateless reset tokens: "<customer id>.<expires>.<hmac>". The HMAC covers the current password
 * hash, so a token dies as soon as the password changes (single use) and after one hour.
 */
final readonly class SignedPasswordResetTokens implements PasswordResetTokensInterface
{
    private const LIFETIME = 3600;

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private string $secret,
        private CustomerRepositoryInterface $customers,
        private ClockInterface $clock,
    ) {
    }

    public function create(Customer $customer): string
    {
        $id = $customer->getPublicId()->toBase58();
        $expires = $this->clock->now()->getTimestamp() + self::LIFETIME;

        return $id.'.'.$expires.'.'.$this->signature($customer, $id, $expires);
    }

    public function verify(string $token): ?Customer
    {
        $parts = explode('.', $token);
        if (3 !== \count($parts) || !ctype_digit($parts[1]) || (int) $parts[1] < $this->clock->now()->getTimestamp()) {
            return null;
        }
        try {
            $customer = $this->customers->findByPublicId(Uuid::fromBase58($parts[0]));
        } catch (\InvalidArgumentException) {
            return null;
        }

        return null !== $customer && hash_equals($this->signature($customer, $parts[0], (int) $parts[1]), $parts[2]) ? $customer : null;
    }

    private function signature(Customer $customer, string $id, int $expires): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $id.'|'.$expires.'|'.$customer->getPassword(), $this->secret, true)), '+/', '-_'), '=');
    }
}
