<?php

declare(strict_types=1);

namespace App\Application\Customer\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class DefaultAddressesInput
{
    #[Assert\NotBlank(message: 'Choose the default billing address.')]
    public string $billingId = '';

    #[Assert\NotBlank(message: 'Choose the default delivery address.')]
    public string $shippingId = '';
}
