<?php

declare(strict_types=1);

namespace App\UI\Http\Api;

use App\Application\Bus\CommandBusInterface;
use App\Application\Bus\QueryBusInterface;
use App\Application\Ordering\Checkout\CheckoutInput;
use App\Application\Ordering\Checkout\GetCheckout;
use App\Application\Ordering\Checkout\GetOrderConfirmation;
use App\Application\Ordering\Checkout\PlaceOrder;
use App\UI\Http\Security\RateLimitGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Checkout wizard API (architecture.html → Checkout flow): thin, validate and dispatch.
 */
final class CheckoutController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    #[Route('/api/checkout', name: 'api_checkout', methods: ['GET'])]
    public function checkout(#[MapQueryParameter] ?string $country = null): JsonResponse
    {
        return $this->json($this->queryBus->ask(new GetCheckout($country)));
    }

    #[Route('/api/checkout', name: 'api_checkout_place', methods: ['POST'])]
    public function place(Request $request, #[MapRequestPayload] CheckoutInput $input, #[Target('checkout.limiter')] RateLimiterFactoryInterface $limiter): JsonResponse
    {
        RateLimitGuard::consume($limiter, $request->getClientIp() ?? 'unknown');

        return $this->json($this->commandBus->dispatch(new PlaceOrder($input)), 201);
    }

    #[Route('/api/orders/{id}', name: 'api_order_confirmation', methods: ['GET'])]
    public function order(string $id): JsonResponse
    {
        return $this->json($this->queryBus->ask(new GetOrderConfirmation($id)));
    }
}
