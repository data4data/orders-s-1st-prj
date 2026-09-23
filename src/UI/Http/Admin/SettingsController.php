<?php

declare(strict_types=1);

namespace App\UI\Http\Admin;

use App\Application\Bus\CommandBusInterface;
use App\Application\Bus\QueryBusInterface;
use App\Application\Tenancy\Settings\AddStoreDomain;
use App\Application\Tenancy\Settings\AddStoreStaff;
use App\Application\Tenancy\Settings\ChangeStaffRole;
use App\Application\Tenancy\Settings\GetStoreSettings;
use App\Application\Tenancy\Settings\MakeDomainPrimary;
use App\Application\Tenancy\Settings\RemoveStoreDomain;
use App\Application\Tenancy\Settings\RemoveStoreStaff;
use App\Application\Tenancy\Settings\SaveShippingMethod;
use App\Application\Tenancy\Settings\ShippingMethodInput;
use App\Application\Tenancy\Settings\StaffMemberInput;
use App\Application\Tenancy\Settings\StoreProfileInput;
use App\Application\Tenancy\Settings\UpdateNotifications;
use App\Application\Tenancy\Settings\UpdateStoreProfile;
use App\Application\Tenancy\Settings\UsePaymentGateway;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Settings of the selected store, for managers and owners. Every write answers with all settings.
 */
#[Route('/api/admin/settings')]
#[IsGranted('ROLE_STORE_MANAGER')]
final class SettingsController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    #[Route('', name: 'admin_settings', methods: ['GET'])]
    public function settings(): JsonResponse
    {
        return $this->json($this->queryBus->ask(new GetStoreSettings()));
    }

    #[Route('/profile', name: 'admin_settings_profile', methods: ['PUT'])]
    public function profile(#[MapRequestPayload] StoreProfileInput $input): JsonResponse
    {
        return $this->after(new UpdateStoreProfile($input));
    }

    #[Route('/domains', name: 'admin_settings_domain_add', methods: ['POST'])]
    public function addDomain(Request $request): JsonResponse
    {
        return $this->after(new AddStoreDomain($request->getPayload()->getString('host')));
    }

    #[Route('/domains/{id}', name: 'admin_settings_domain_remove', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function removeDomain(int $id): JsonResponse
    {
        return $this->after(new RemoveStoreDomain($id));
    }

    #[Route('/domains/{id}/primary', name: 'admin_settings_domain_primary', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function primaryDomain(int $id): JsonResponse
    {
        return $this->after(new MakeDomainPrimary($id));
    }

    #[Route('/shipping-methods', name: 'admin_settings_shipping_create', methods: ['POST'])]
    public function createShipping(#[MapRequestPayload] ShippingMethodInput $input): JsonResponse
    {
        return $this->after(new SaveShippingMethod(null, $input));
    }

    #[Route('/shipping-methods/{id}', name: 'admin_settings_shipping_update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function updateShipping(int $id, #[MapRequestPayload] ShippingMethodInput $input): JsonResponse
    {
        return $this->after(new SaveShippingMethod($id, $input));
    }

    #[Route('/payment-gateway', name: 'admin_settings_gateway', methods: ['PUT'])]
    public function gateway(Request $request): JsonResponse
    {
        return $this->after(new UsePaymentGateway($request->getPayload()->getString('code')));
    }

    #[Route('/staff', name: 'admin_settings_staff_add', methods: ['POST'])]
    public function addStaff(#[MapRequestPayload] StaffMemberInput $input): JsonResponse
    {
        return $this->after(new AddStoreStaff($input));
    }

    #[Route('/staff/{id}', name: 'admin_settings_staff_role', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function changeRole(Request $request, int $id): JsonResponse
    {
        return $this->after(new ChangeStaffRole($id, $request->getPayload()->getString('role')));
    }

    #[Route('/staff/{id}', name: 'admin_settings_staff_remove', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function removeStaff(int $id): JsonResponse
    {
        return $this->after(new RemoveStoreStaff($id));
    }

    #[Route('/notifications', name: 'admin_settings_notifications', methods: ['PUT'])]
    public function notifications(Request $request): JsonResponse
    {
        /** @var array<string, bool> $values */
        $values = array_filter($request->getPayload()->all(), 'is_bool');

        return $this->after(new UpdateNotifications($values));
    }

    private function after(object $command): JsonResponse
    {
        $this->commandBus->dispatch($command);

        return $this->json($this->queryBus->ask(new GetStoreSettings()));
    }
}
