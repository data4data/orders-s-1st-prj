<?php

declare(strict_types=1);

namespace App\UI\Http\Api;

use App\Application\Bus\CommandBusInterface;
use App\Application\Bus\QueryBusInterface;
use App\Application\Ordering\Cart\AddToCart;
use App\Application\Ordering\Cart\ApplyCoupon;
use App\Application\Ordering\Cart\GetCart;
use App\Application\Ordering\Cart\RemoveCartLine;
use App\Application\Ordering\Cart\RemoveCoupon;
use App\Application\Ordering\Cart\UpdateCartLine;
use App\UI\Http\Security\RateLimitGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The cart (a draft order) of the visitor. Every call answers with the whole cart.
 */
#[Route('/api/cart')]
final class CartController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    #[Route('', name: 'api_cart', methods: ['GET'])]
    public function cart(): JsonResponse
    {
        return $this->json($this->queryBus->ask(new GetCart()));
    }

    #[Route('/lines', name: 'api_cart_add', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        $data = $request->getPayload();

        return $this->json($this->commandBus->dispatch(new AddToCart($data->getString('variantId'), $data->getInt('quantity', 1))));
    }

    #[Route('/lines/{variantId}', name: 'api_cart_update', methods: ['PATCH'])]
    public function update(Request $request, string $variantId): JsonResponse
    {
        return $this->json($this->commandBus->dispatch(new UpdateCartLine($variantId, $request->getPayload()->getInt('quantity'))));
    }

    #[Route('/lines/{variantId}', name: 'api_cart_remove', methods: ['DELETE'])]
    public function remove(string $variantId): JsonResponse
    {
        return $this->json($this->commandBus->dispatch(new RemoveCartLine($variantId)));
    }

    #[Route('/coupon', name: 'api_cart_coupon', methods: ['POST'])]
    public function applyCoupon(Request $request, #[Target('coupon_apply.limiter')] RateLimiterFactoryInterface $limiter): JsonResponse
    {
        RateLimitGuard::consume($limiter, $request->getClientIp() ?? 'unknown');

        return $this->json($this->commandBus->dispatch(new ApplyCoupon($request->getPayload()->getString('code'))));
    }

    #[Route('/coupon', name: 'api_cart_coupon_remove', methods: ['DELETE'])]
    public function removeCoupon(): JsonResponse
    {
        return $this->json($this->commandBus->dispatch(new RemoveCoupon()));
    }
}
