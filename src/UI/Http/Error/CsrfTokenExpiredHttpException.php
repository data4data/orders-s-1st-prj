<?php

declare(strict_types=1);

namespace App\UI\Http\Error;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * 419: the CSRF token is missing or no longer valid (usually an expired session). Not an
 * official status code; used so the client can tell it apart from a real 403 and refresh.
 */
final class CsrfTokenExpiredHttpException extends HttpException
{
    public const STATUS = 419;

    public function __construct()
    {
        parent::__construct(self::STATUS, 'Your session expired. Please try again.');
    }
}
