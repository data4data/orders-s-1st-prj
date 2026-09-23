<?php

declare(strict_types=1);

namespace App\UI\Http\Api;

use App\UI\Http\Security\ApiCsrfListener;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Returns a fresh API CSRF token. Same-origin only (browsers block other sites from reading it).
 */
final class CsrfTokenController extends AbstractController
{
    #[Route('/api/csrf-token', name: 'api_csrf_token', methods: ['GET'])]
    public function __invoke(CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        return $this->json(['token' => $csrfTokenManager->getToken(ApiCsrfListener::TOKEN_ID)->getValue()], headers: ['Cache-Control' => 'no-store']);
    }
}
