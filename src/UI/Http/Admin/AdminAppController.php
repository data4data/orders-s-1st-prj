<?php

declare(strict_types=1);

namespace App\UI\Http\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Admin host pages: the Twig login page, and one shell page for every admin URL that
 * Vue Router then takes over (decision #20).
 */
final class AdminAppController extends AbstractController
{
    private const ADMIN_HOST = "request.getHost() matches '/^admin\\\\./'";

    #[Route('/login', name: 'admin_login_page', methods: ['GET', 'POST'], condition: self::ADMIN_HOST)]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('admin_app');
        }

        $error = $authenticationUtils->getLastAuthenticationError();

        return $this->render('admin/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $error?->getMessageKey(),
            'error_data' => $error?->getMessageData() ?? [],
        ], new Response(status: null === $error ? 200 : 422));
    }

    #[Route('/{path}', name: 'admin_app', requirements: ['path' => '(?!api/|build/|_|login$)[^.]*'], defaults: ['path' => ''], methods: ['GET'], condition: self::ADMIN_HOST, priority: -10)]
    public function app(): Response
    {
        return $this->render('vue/admin.html.twig');
    }
}
