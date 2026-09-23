<?php

declare(strict_types=1);

namespace App\UI\Http\Admin;

use App\Application\Bus\CommandBusInterface;
use App\Application\Bus\QueryBusInterface;
use App\Application\Platform\AddCountry;
use App\Application\Platform\AddTaxRate;
use App\Application\Platform\CreateStaffUser;
use App\Application\Platform\CreateStore;
use App\Application\Platform\CreateTaxCategory;
use App\Application\Platform\DeleteFailedMessage;
use App\Application\Platform\GetPlatformOverview;
use App\Application\Platform\GetSystemStatus;
use App\Application\Platform\RetryFailedMessage;
use App\Application\Platform\SetStoreActive;
use App\Application\Platform\SetSuperAdmin;
use App\Application\Platform\StaffUserInput;
use App\Application\Platform\StoreInput;
use App\Application\Platform\TaxRateInput;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Platform pages for super-admins: stores, countries and VAT, tax categories, staff users, system.
 */
#[Route('/api/admin/platform')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class PlatformController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    #[Route('', name: 'admin_platform', methods: ['GET'])]
    public function overview(): JsonResponse
    {
        return $this->json($this->queryBus->ask(new GetPlatformOverview()));
    }

    #[Route('/stores', name: 'admin_platform_store_create', methods: ['POST'])]
    public function createStore(#[MapRequestPayload] StoreInput $input): JsonResponse
    {
        return $this->after(new CreateStore($input), 201);
    }

    #[Route('/stores/{id}/active', name: 'admin_platform_store_active', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function storeActive(Request $request, int $id): JsonResponse
    {
        return $this->after(new SetStoreActive($id, $request->getPayload()->getBoolean('active')));
    }

    #[Route('/countries', name: 'admin_platform_country_add', methods: ['POST'])]
    public function addCountry(Request $request): JsonResponse
    {
        $data = $request->getPayload();

        return $this->after(new AddCountry($data->getString('code'), $data->getString('name'), $data->getBoolean('isEu', true)), 201);
    }

    #[Route('/tax-rates', name: 'admin_platform_rate_add', methods: ['POST'])]
    public function addRate(#[MapRequestPayload] TaxRateInput $input): JsonResponse
    {
        return $this->after(new AddTaxRate($input), 201);
    }

    #[Route('/tax-categories', name: 'admin_platform_category_add', methods: ['POST'])]
    public function addCategory(Request $request): JsonResponse
    {
        return $this->after(new CreateTaxCategory($request->getPayload()->getString('code'), $request->getPayload()->getString('name')), 201);
    }

    #[Route('/staff-users', name: 'admin_platform_staff_add', methods: ['POST'])]
    public function addStaff(#[MapRequestPayload] StaffUserInput $input): JsonResponse
    {
        return $this->after(new CreateStaffUser($input), 201);
    }

    #[Route('/staff-users/{id}/super-admin', name: 'admin_platform_staff_super', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function superAdmin(Request $request, int $id): JsonResponse
    {
        return $this->after(new SetSuperAdmin($id, $request->getPayload()->getBoolean('superAdmin')));
    }

    #[Route('/system', name: 'admin_platform_system', methods: ['GET'])]
    public function system(): JsonResponse
    {
        return $this->json($this->queryBus->ask(new GetSystemStatus()));
    }

    #[Route('/system/failed/{id}/retry', name: 'admin_platform_failed_retry', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function retry(int $id): JsonResponse
    {
        $this->commandBus->dispatch(new RetryFailedMessage($id));

        return $this->system();
    }

    #[Route('/system/failed/{id}', name: 'admin_platform_failed_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function deleteFailed(int $id): JsonResponse
    {
        $this->commandBus->dispatch(new DeleteFailedMessage($id));

        return $this->system();
    }

    private function after(object $command, int $status = 200): JsonResponse
    {
        $this->commandBus->dispatch($command);

        return $this->json($this->queryBus->ask(new GetPlatformOverview()), $status);
    }
}
