<?php

declare(strict_types=1);

namespace App\UI\Http\Web;

use App\Application\Bus\QueryBusInterface;
use App\Application\Content\ContactInput;
use App\Application\Content\GetContentPage;
use App\Application\Content\GetLandingPage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Content pages of every shop: Twig + Bootstrap + jQuery (decision #20).
 */
final class ContentPageController extends AbstractController
{
    private const SHOP_HOST = "not (request.getHost() matches '/^admin\\\\./')";

    public function __construct(private readonly QueryBusInterface $queryBus)
    {
    }

    #[Route('/', name: 'home', methods: ['GET'], condition: self::SHOP_HOST)]
    public function home(): Response
    {
        return $this->render('content/home.html.twig', ['page' => $this->queryBus->ask(new GetLandingPage())]);
    }

    #[Route('/about', name: 'about', methods: ['GET'], condition: self::SHOP_HOST)]
    #[Route('/faq', name: 'faq', methods: ['GET'], condition: self::SHOP_HOST)]
    #[Route('/terms', name: 'terms', methods: ['GET'], condition: self::SHOP_HOST)]
    #[Route('/privacy', name: 'privacy', methods: ['GET'], condition: self::SHOP_HOST)]
    public function text(string $_route): Response
    {
        return $this->render(sprintf('content/%s.html.twig', $_route));
    }

    #[Route('/contact', name: 'contact', methods: ['GET'], condition: self::SHOP_HOST)]
    public function contact(): Response
    {
        return $this->render('content/contact.html.twig', ['subjects' => ContactInput::SUBJECTS]);
    }

    #[Route('/shipping-info', name: 'shipping_info', methods: ['GET'], condition: self::SHOP_HOST)]
    public function shipping(): Response
    {
        return $this->render('content/shipping_info.html.twig', $this->queryBus->ask(new GetContentPage('shipping')));
    }

    #[Route('/safety-data-sheets', name: 'safety_data_sheets', methods: ['GET'], condition: self::SHOP_HOST)]
    public function safetyDataSheets(): Response
    {
        return $this->render('content/safety_data_sheets.html.twig', $this->queryBus->ask(new GetContentPage('safety-data-sheets')));
    }
}
