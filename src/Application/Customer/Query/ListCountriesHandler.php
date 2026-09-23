<?php

declare(strict_types=1);

namespace App\Application\Customer\Query;

use App\Application\Customer\Port\CountryRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListCountriesHandler
{
    public function __construct(private CountryRepositoryInterface $countries)
    {
    }

    /**
     * @return array<string, string>
     */
    public function __invoke(ListCountries $query): array
    {
        $list = [];
        foreach ($this->countries->findAll() as $country) {
            $list[$country->getCode()] = $country->getName();
        }

        return $list;
    }
}
