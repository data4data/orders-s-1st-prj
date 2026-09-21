<?php

declare(strict_types=1);

namespace App\UI\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * RFC 7807 problem+json error response (decision #50). The full API error layer follows in Phase 3.
 */
final class ProblemResponse
{
    public static function create(int $status, string $detail): JsonResponse
    {
        return new JsonResponse(
            ['type' => 'about:blank', 'title' => Response::$statusTexts[$status] ?? 'Error', 'status' => $status, 'detail' => $detail],
            $status,
            ['Content-Type' => 'application/problem+json'],
        );
    }
}
