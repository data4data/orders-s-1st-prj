<?php

declare(strict_types=1);

namespace App\UI\Http\Admin;

use App\UI\Http\ProblemResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * JSON login for staff on the admin host. The firewall (json_login) authenticates the request;
 * this action only runs after a successful login.
 */
final class AdminAuthController extends AbstractController
{
    #[Route('/api/admin/login', name: 'admin_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        $user = $this->getUser();
        if (null === $user) {
            return ProblemResponse::create(401, 'Send {"username": "...", "password": "..."} as JSON.');
        }

        return $this->json(['email' => $user->getUserIdentifier(), 'roles' => $user->getRoles()]);
    }

    #[Route('/api/admin/logout', name: 'admin_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('Handled by the firewall logout listener.');
    }
}
