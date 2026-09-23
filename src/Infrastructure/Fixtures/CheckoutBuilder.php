<?php

declare(strict_types=1);

namespace App\Infrastructure\Fixtures;

use App\Domain\Customer\AddressBookPolicy;
use App\Domain\Discount\CouponType;
use App\Entity\Country;
use App\Entity\Coupon;
use App\Entity\Customer;
use App\Entity\CustomerAddress;
use App\Entity\ShippingMethod;
use App\Entity\Store;
use App\Entity\TaxCategory;
use App\Infrastructure\Fixtures\Factory\CustomerFactory;
use App\Infrastructure\Tenancy\TenantContext;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Demo shipping methods, coupons and customers for stories and tests. Shipping amounts are net
 * cents, chosen so the gross prices are tidy (578 net → €6.99 incl. 21% VAT).
 */
final readonly class CheckoutBuilder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TenantContext $tenantContext,
        private AddressBookPolicy $policy,
    ) {
    }

    public function shippingAndCoupons(Store $store, TaxCategory $standardVat, bool $pallets = false): void
    {
        $this->tenantContext->runAsStore($store, function () use ($standardVat, $pallets): void {
            $methods = [
                new ShippingMethod('standard', 'PostNL Standard', 'free_over_threshold', ['amount' => 578, 'threshold' => 10000], $standardVat, ['NL', 'BE'], 1, '1–2 working days, free from €100'),
                new ShippingMethod('express', 'Next-day Express', 'flat', ['amount' => 1239], $standardVat, ['NL'], 2, 'Ordered before 17:00, delivered tomorrow'),
                new ShippingMethod('europe', 'DHL Europe', 'weight_based', ['brackets' => [
                    ['up_to_grams' => 10000, 'amount' => 1239],
                    ['up_to_grams' => 31500, 'amount' => 2065],
                    ['up_to_grams' => null, 'amount' => 4131],
                ]], $standardVat, ['BE', 'DE'], 3, '2–4 working days, price by weight'),
            ];
            if ($pallets) {
                $methods[] = new ShippingMethod('pallet', 'Pallet delivery', 'flat', ['amount' => 7430], $standardVat, ['NL', 'BE', 'DE'], 4, 'For drums and bulk, tail-lift truck');
            }
            foreach ($methods as $method) {
                $this->entityManager->persist($method);
            }

            $welcome = new Coupon('WELCOME10', CouponType::Percentage, percent: '10.00');
            $welcome->limit(2500, null, null, null);
            $fiveOff = new Coupon('FIVEOFF', CouponType::Fixed, amount: 500);
            $expired = new Coupon('SUMMER2025', CouponType::Percentage, percent: '15.00');
            $expired->limit(null, new \DateTimeImmutable('2025-06-01'), new \DateTimeImmutable('2025-08-31 23:59:59'), null);
            foreach ([$welcome, $fiveOff, $expired] as $coupon) {
                $this->entityManager->persist($coupon);
            }
            $this->entityManager->flush();
        });
    }

    /**
     * A customer with one address used for billing and delivery, plus an optional second delivery address.
     *
     * @param array{street: string, houseNumber: string, postcode: string, city: string, company?: string, vatId?: string} $address
     * @param array{street: string, houseNumber: string, postcode: string, city: string, label?: string}|null              $workshop
     */
    public function customer(Store $store, Country $country, string $email, string $firstName, string $lastName, array $address, ?array $workshop = null): Customer
    {
        return $this->tenantContext->runAsStore($store, function () use ($country, $email, $firstName, $lastName, $address, $workshop): Customer {
            $customer = CustomerFactory::createOne(['email' => $email, 'firstName' => $firstName, 'lastName' => $lastName]);
            $home = new CustomerAddress($customer, $country);
            $home->update('Home', $firstName, $lastName, $address['company'] ?? null, $address['vatId'] ?? null, $address['street'], $address['houseNumber'], $address['postcode'], $address['city'], $country, '+31 20 123 4567', true, true);
            $customer->addAddress($home);
            $delivery = $home;
            if (null !== $workshop) {
                $delivery = new CustomerAddress($customer, $country);
                $delivery->update($workshop['label'] ?? 'Workshop', $firstName, $lastName, null, null, $workshop['street'], $workshop['houseNumber'], $workshop['postcode'], $workshop['city'], $country, null, false, true);
                $customer->addAddress($delivery);
            }
            $this->entityManager->flush();
            $customer->setDefaults($home, $delivery, $this->policy);
            $this->entityManager->flush();

            return $customer;
        });
    }
}
