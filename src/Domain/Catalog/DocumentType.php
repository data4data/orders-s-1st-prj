<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

/**
 * product_document.type: safety data sheet, technical data sheet, or an OEM approval letter.
 */
enum DocumentType: string
{
    case SafetyDataSheet = 'sds';
    case TechnicalDataSheet = 'tds';
    case Approval = 'approval';
}
