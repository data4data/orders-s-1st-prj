<?php

declare(strict_types=1);

namespace App\UI\Http\Error;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * RFC 7807 application/problem+json (decision #50). Every API error has this shape, so the
 * frontend handles all of them in one place (ApiClient / api()).
 */
final class ProblemDetails
{
    public const CONTENT_TYPE = 'application/problem+json';

    /**
     * @param list<array{propertyPath: string, message: string}> $violations
     * @param array<string, string>                              $headers
     */
    public static function response(int $status, string $detail, array $violations = [], ?string $requestId = null, array $headers = []): JsonResponse
    {
        $body = [
            'type' => 'about:blank',
            'title' => Response::$statusTexts[$status] ?? (419 === $status ? 'Session Expired' : 'Error'),
            'status' => $status,
            'detail' => $detail,
        ];
        if ([] !== $violations) {
            $body['violations'] = $violations;
        }
        if (null !== $requestId) {
            $body['requestId'] = $requestId;
        }
        if (isset($headers['Retry-After'])) {
            $body['retryAfter'] = (int) $headers['Retry-After'];
        }

        return new JsonResponse($body, $status, ['Content-Type' => self::CONTENT_TYPE] + $headers);
    }
}
