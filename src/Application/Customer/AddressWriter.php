<?php

declare(strict_types=1);

namespace App\Application\Customer;

use App\Application\Customer\Input\AddressInput;
use App\Application\Customer\Port\CountryRepositoryInterface;
use App\Application\Validation\ValidationException;
use App\Entity\Country;
use App\Entity\CustomerAddress;
use App\Entity\Embeddable\PostalAddress;

/**
 * Copies an AddressInput onto an address book entry or an order snapshot.
 */
final readonly class AddressWriter
{
    public function __construct(private CountryRepositoryInterface $countries)
    {
    }

    public function write(AddressInput $input, CustomerAddress $address, string $path = ''): void
    {
        $address->update(
            self::blankToNull($input->label), trim($input->firstName), trim($input->lastName), self::blankToNull($input->company),
            self::blankToNull($input->vatId), trim($input->street), trim($input->houseNumber), self::postcode($input->postcode),
            trim($input->city), $this->country($input->countryCode, $path), self::blankToNull($input->phone),
            $input->usableForBilling, $input->usableForShipping,
        );
    }

    public function snapshot(AddressInput $input, string $path = ''): PostalAddress
    {
        $country = $this->country($input->countryCode, $path);

        return new PostalAddress(
            trim($input->firstName), trim($input->lastName), self::blankToNull($input->company), self::blankToNull($input->vatId),
            trim($input->street), trim($input->houseNumber), self::postcode($input->postcode), trim($input->city), $country->getCode(),
            self::blankToNull($input->phone),
        );
    }

    public function country(string $code, string $path = ''): Country
    {
        return $this->countries->findByCode($code)
            ?? throw ValidationException::forField(('' !== $path ? $path.'.' : '').'countryCode', 'We do not deliver to this country.');
    }

    private static function postcode(string $postcode): string
    {
        $postcode = strtoupper(trim($postcode));

        // Dutch postcodes are stored the way they are written: "1012 AB".
        return preg_match('/^(\d{4})\s?([A-Z]{2})$/', $postcode, $m) ? $m[1].' '.$m[2] : $postcode;
    }

    private static function blankToNull(?string $value): ?string
    {
        $value = null !== $value ? trim($value) : null;

        return '' === $value ? null : $value;
    }
}
