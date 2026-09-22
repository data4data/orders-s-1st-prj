<?php

declare(strict_types=1);

namespace App\UI\Http\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Shop start page. Until the landing page arrives (Phase 7) it opens the catalog.
 */
final class HomeController extends AbstractController
{
    #[Route('/', name: 'home', methods: ['GET'], condition: "not (request.getHost() matches '/^admin\\\\./')")]
    public function home(): RedirectResponse
    {
        return $this->redirectToRoute('catalog_all');
    }
}
