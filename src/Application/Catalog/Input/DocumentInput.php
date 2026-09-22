<?php

declare(strict_types=1);

namespace App\Application\Catalog\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class DocumentInput
{
    #[Assert\Choice(choices: ['sds', 'tds', 'approval'], message: 'Choose a document type.')]
    public string $type = 'sds';

    #[Assert\NotBlank(message: 'Enter a title.')]
    #[Assert\Length(max: 200)]
    public string $title = '';

    #[Assert\NotBlank(message: 'Enter the document address.')]
    #[Assert\Url(message: 'Enter a full address starting with https://.', requireTld: true, protocols: ['https'])]
    #[Assert\Length(max: 500)]
    public string $url = '';

    #[Assert\Regex(pattern: '/^[a-z]{2}$/', message: 'Use a two-letter language code, e.g. en.')]
    public string $locale = 'en';
}
