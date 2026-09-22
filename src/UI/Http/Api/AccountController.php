<?php

declare(strict_types=1);

namespace App\UI\Http\Api;

use App\Application\Bus\CommandBusInterface;
use App\Application\Bus\QueryBusInterface;
use App\Application\Customer\Account\ChangePassword;
use App\Application\Customer\Account\DeleteAddress;
use App\Application\Customer\Account\GetAccount;
use App\Application\Customer\Account\GetMyOrder;
use App\Application\Customer\Account\ListMyOrders;
use App\Application\Customer\Account\SaveAddress;
use App\Application\Customer\Account\SetDefaultAddresses;
use App\Application\Customer\Account\UpdateProfile;
use App\Application\Customer\Input\AddressInput;
use App\Application\Customer\Input\ChangePasswordInput;
use App\Application\Customer\Input\DefaultAddressesInput;
use App\Application\Customer\Input\ProfileInput;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The logged-in customer's account (access_control: ROLE_CUSTOMER).
 */
#[Route('/api/account')]
final class AccountController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    #[Route('', name: 'api_account', methods: ['GET'])]
    public function account(): JsonResponse
    {
        return $this->json($this->queryBus->ask(new GetAccount()));
    }

    #[Route('/profile', name: 'api_account_profile', methods: ['PUT'])]
    public function profile(#[MapRequestPayload] ProfileInput $input): Response
    {
        $this->commandBus->dispatch(new UpdateProfile($input));

        return new Response(status: 204);
    }

    #[Route('/password', name: 'api_account_password', methods: ['PUT'])]
    public function password(#[MapRequestPayload] ChangePasswordInput $input): Response
    {
        $this->commandBus->dispatch(new ChangePassword($input));

        return new Response(status: 204);
    }

    #[Route('/addresses', name: 'api_account_address_create', methods: ['POST'])]
    public function createAddress(#[MapRequestPayload] AddressInput $input): JsonResponse
    {
        return $this->json($this->commandBus->dispatch(new SaveAddress(null, $input)), 201);
    }

    #[Route('/addresses/defaults', name: 'api_account_address_defaults', methods: ['PUT'])]
    public function defaults(#[MapRequestPayload] DefaultAddressesInput $input): JsonResponse
    {
        return $this->json($this->commandBus->dispatch(new SetDefaultAddresses($input)));
    }

    #[Route('/addresses/{id}', name: 'api_account_address_update', methods: ['PUT'])]
    public function updateAddress(string $id, #[MapRequestPayload] AddressInput $input): JsonResponse
    {
        return $this->json($this->commandBus->dispatch(new SaveAddress($id, $input)));
    }

    #[Route('/addresses/{id}', name: 'api_account_address_delete', methods: ['DELETE'])]
    public function deleteAddress(string $id): JsonResponse
    {
        return $this->json($this->commandBus->dispatch(new DeleteAddress($id)));
    }

    #[Route('/orders', name: 'api_account_orders', methods: ['GET'])]
    public function orders(#[MapQueryParameter] int $page = 1): JsonResponse
    {
        return $this->json($this->queryBus->ask(new ListMyOrders($page)));
    }

    #[Route('/orders/{id}', name: 'api_account_order', methods: ['GET'])]
    public function order(string $id): JsonResponse
    {
        return $this->json($this->queryBus->ask(new GetMyOrder($id)));
    }
}
