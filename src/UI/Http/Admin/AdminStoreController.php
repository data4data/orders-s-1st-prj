<?php

declare(strict_types=1);

namespace App\UI\Http\Admin;

use App\Application\Bus\CommandBusInterface;
use App\Application\Bus\QueryBusInterface;
use App\Application\Tenancy\Command\SwitchAdminStore;
use App\Application\Tenancy\Exception\StoreAccessDeniedException;
use App\Application\Tenancy\Exception\StoreNotFoundException;
use App\Application\Tenancy\Query\GetTenantStatus;
use App\Application\Tenancy\Query\ListAdminStores;
use App\UI\Http\ProblemResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin store switcher: which stores the staff member may pick, and which one is active.
 */
#[Route('/api/admin/stores')]
final class AdminStoreController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    #[Route('', name: 'admin_stores_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->queryBus->ask(new ListAdminStores($this->staffEmail())));
    }

    #[Route('/current', name: 'admin_stores_current', methods: ['GET'])]
    public function current(): JsonResponse
    {
        return $this->json($this->queryBus->ask(new GetTenantStatus()));
    }

    /**
     * Body: {"store": "<store public id>"} or {"store": "all"} (super-admin only, read-only view).
     */
    #[Route('/current', name: 'admin_stores_switch', methods: ['PUT'])]
    public function switch(Request $request): JsonResponse
    {
        $store = $request->toArray()['store'] ?? null;
        if (!\is_string($store) || '' === $store) {
            return ProblemResponse::create(422, 'Send {"store": "<store public id>"} or {"store": "all"}.');
        }

        try {
            $this->commandBus->dispatch(new SwitchAdminStore($this->staffEmail(), 'all' === $store ? null : $store));
        } catch (StoreAccessDeniedException $exception) {
            return ProblemResponse::create(403, $exception->getMessage());
        } catch (StoreNotFoundException $exception) {
            return ProblemResponse::create(404, $exception->getMessage());
        }

        return new JsonResponse(null, 204);
    }

    private function staffEmail(): string
    {
        return $this->getUser()?->getUserIdentifier() ?? throw $this->createAccessDeniedException();
    }
}
