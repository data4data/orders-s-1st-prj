<?php

declare(strict_types=1);

namespace App\UI\Http\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page shells for the Vue islands Cart, Checkout, OrderConfirmation and Account; the data comes
 * from /api (decision #20).
 */
final class ShopPageController extends AbstractController
{
    private const SHOP_HOST = "not (request.getHost() matches '/^admin\\\\./')";

    #[Route('/cart', name: 'cart', methods: ['GET'], condition: self::SHOP_HOST)]
    public function cart(): Response
    {
        return $this->island('Cart', 'cart.title');
    }

    #[Route('/checkout', name: 'checkout', methods: ['GET'], condition: self::SHOP_HOST)]
    public function checkout(): Response
    {
        return $this->island('Checkout', 'checkout.title', ['loginUrl' => $this->generateUrl('customer_login', ['_target_path' => '/checkout'])]);
    }

    #[Route('/order/{id}', name: 'order_confirmation', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['GET'], condition: self::SHOP_HOST)]
    public function confirmation(string $id): Response
    {
        return $this->island('OrderConfirmation', 'order.confirmation_title', ['id' => $id]);
    }

    #[Route('/account/{section}', name: 'account', requirements: ['section' => 'orders|addresses|profile'], defaults: ['section' => null], methods: ['GET'], condition: self::SHOP_HOST)]
    public function account(?string $section = null): Response
    {
        return $this->island('Account', 'account.title', ['section' => $section ?? 'dashboard']);
    }

    /**
     * @param array<string, mixed> $props
     */
    private function island(string $page, string $titleKey, array $props = []): Response
    {
        return $this->render('storefront/island.html.twig', ['page' => $page, 'title_key' => $titleKey, 'props' => $props]);
    }
}
