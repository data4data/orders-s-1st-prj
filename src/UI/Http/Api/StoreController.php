<?php

declare(strict_types=1);

namespace App\UI\Http\Api;

use App\Application\Bus\QueryBusInterface;
use App\Application\Tenancy\Query\GetTenantStatus;
use App\Application\Tenancy\View\TenantStatusView;
use App\UI\Http\Error\ProblemDetails;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public information about the store of the current host: name, locale and branding.
 */
final class StoreController extends AbstractController
{
    public function __construct(private readonly QueryBusInterface $queryBus)
    {
    }

    #[Route('/api/store', name: 'api_store', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        /** @var TenantStatusView $status */
        $status = $this->queryBus->ask(new GetTenantStatus());

        return null !== $status->store
            ? $this->json($status->store)
            : ProblemDetails::response(404, 'No store is selected.');
    }
}
