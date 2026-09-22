<?php

declare(strict_types=1);

namespace App\UI\Http\Admin;

use App\Application\Bus\CommandBusInterface;
use App\Application\Bus\QueryBusInterface;
use App\Application\Ordering\Admin\ApplyOrderTransition;
use App\Application\Ordering\Admin\GetAdminOrder;
use App\Application\Ordering\Admin\GetDashboard;
use App\Application\Ordering\Admin\ListAdminOrders;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin orders and dashboard for the selected store.
 */
#[Route('/api/admin')]
#[IsGranted('ROLE_STORE_STAFF')]
final class OrderAdminController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    #[Route('/dashboard', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        return $this->json($this->queryBus->ask(new GetDashboard()));
    }

    #[Route('/orders', name: 'admin_orders', methods: ['GET'])]
    public function orders(#[MapQueryParameter] ?string $state = null, #[MapQueryParameter] string $q = '', #[MapQueryParameter] int $page = 1): JsonResponse
    {
        return $this->json($this->queryBus->ask(new ListAdminOrders($state, $q, $page)));
    }

    #[Route('/orders/{id}', name: 'admin_order', methods: ['GET'])]
    public function order(string $id): JsonResponse
    {
        return $this->json($this->queryBus->ask(new GetAdminOrder($id)));
    }

    #[Route('/orders/{id}/transitions', name: 'admin_order_transition', methods: ['POST'])]
    public function transition(Request $request, string $id): JsonResponse
    {
        $data = $request->getPayload();
        $comment = trim($data->getString('comment'));

        return $this->json($this->commandBus->dispatch(new ApplyOrderTransition(
            $id,
            $data->getString('transition'),
            '' === $comment ? null : $comment,
            $data->has('version') ? $data->getInt('version') : null,
        )));
    }
}
