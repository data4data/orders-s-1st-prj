<?php

declare(strict_types=1);

namespace App\UI\Http\Admin;

use App\Application\Bus\CommandBusInterface;
use App\Application\Bus\QueryBusInterface;
use App\Application\Customer\Admin\GetAdminCustomer;
use App\Application\Customer\Admin\ListAdminCustomers;
use App\Application\Customer\Admin\ListContactMessages;
use App\Application\Customer\Admin\MarkContactMessageRead;
use App\Application\Ordering\Admin\CouponInput;
use App\Application\Ordering\Admin\ListCoupons;
use App\Application\Ordering\Admin\SaveCoupon;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin → Customers (with the Contact messages tab) and Coupons of the selected store.
 */
#[Route('/api/admin')]
#[IsGranted('ROLE_STORE_STAFF')]
final class CustomerAdminController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    #[Route('/customers', name: 'admin_customers', methods: ['GET'])]
    public function customers(#[MapQueryParameter] string $q = '', #[MapQueryParameter] int $page = 1): JsonResponse
    {
        return $this->json($this->queryBus->ask(new ListAdminCustomers($q, $page)));
    }

    #[Route('/customers/{id}', name: 'admin_customer', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['GET'])]
    public function customer(string $id): JsonResponse
    {
        return $this->json($this->queryBus->ask(new GetAdminCustomer($id)));
    }

    #[Route('/contact-messages', name: 'admin_contact_messages', methods: ['GET'])]
    public function messages(#[MapQueryParameter] bool $unread = false, #[MapQueryParameter] int $page = 1): JsonResponse
    {
        return $this->json($this->queryBus->ask(new ListContactMessages($unread, $page)));
    }

    #[Route('/contact-messages/{id}/read', name: 'admin_contact_message_read', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function read(int $id): Response
    {
        $this->commandBus->dispatch(new MarkContactMessageRead($id));

        return new Response(status: 204);
    }

    #[Route('/coupons', name: 'admin_coupons', methods: ['GET'])]
    public function coupons(): JsonResponse
    {
        return $this->json($this->queryBus->ask(new ListCoupons()));
    }

    #[Route('/coupons', name: 'admin_coupon_create', methods: ['POST'])]
    #[IsGranted('ROLE_STORE_MANAGER')]
    public function createCoupon(#[MapRequestPayload] CouponInput $input): JsonResponse
    {
        return $this->json(['id' => $this->commandBus->dispatch(new SaveCoupon(null, $input))], 201);
    }

    #[Route('/coupons/{id}', name: 'admin_coupon_update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    #[IsGranted('ROLE_STORE_MANAGER')]
    public function updateCoupon(int $id, #[MapRequestPayload] CouponInput $input): JsonResponse
    {
        return $this->json(['id' => $this->commandBus->dispatch(new SaveCoupon($id, $input))]);
    }
}
