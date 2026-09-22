<?php

declare(strict_types=1);

namespace App\UI\Http\Webhook;

use App\Application\Payment\InvalidWebhookException;
use App\Application\Payment\PaymentGatewayRegistry;
use App\Application\Payment\WebhookRequest;
use App\UI\Http\Error\ProblemDetails;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gateway callbacks. Phase 5 verifies the signature; storing, deduplicating and processing the
 * event (payment capture → order pay) is added in Phase 6.
 */
final class PaymentWebhookController extends AbstractController
{
    public function __construct(private readonly PaymentGatewayRegistry $gateways)
    {
    }

    #[Route('/webhooks/payment/{gateway}', name: 'payment_webhook', requirements: ['gateway' => '[a-z0-9_]+'], methods: ['POST'])]
    public function receive(Request $request, string $gateway): JsonResponse
    {
        $headers = array_map(static fn (array $values) => (string) ($values[0] ?? ''), $request->headers->all());
        try {
            $event = $this->gateways->get($gateway)->handleWebhook(new WebhookRequest($request->getContent(), $headers));
        } catch (InvalidWebhookException $exception) {
            return ProblemDetails::response(400, $exception->getMessage());
        } catch (\LogicException) {
            return ProblemDetails::response(404, sprintf('Unknown payment gateway "%s".', $gateway));
        }

        return $this->json(['received' => $event->eventId], 202);
    }
}
