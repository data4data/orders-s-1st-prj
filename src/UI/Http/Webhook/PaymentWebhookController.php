<?php

declare(strict_types=1);

namespace App\UI\Http\Webhook;

use App\Application\Bus\CommandBusInterface;
use App\Application\Payment\InvalidWebhookException;
use App\Application\Payment\ReceivePaymentWebhook;
use App\UI\Http\Error\ProblemDetails;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gateway callbacks: verify the signature, store the event once, answer 202; a worker processes it.
 * A repeated delivery answers 200 without doing anything.
 */
final class PaymentWebhookController extends AbstractController
{
    public function __construct(private readonly CommandBusInterface $commandBus)
    {
    }

    #[Route('/webhooks/payment/{gateway}', name: 'payment_webhook', requirements: ['gateway' => '[a-z0-9_]+'], methods: ['POST'])]
    public function receive(Request $request, string $gateway): JsonResponse
    {
        $headers = array_map(static fn (array $values) => (string) ($values[0] ?? ''), $request->headers->all());
        try {
            /** @var array{received: string, duplicate: bool} $result */
            $result = $this->commandBus->dispatch(new ReceivePaymentWebhook($gateway, $request->getContent(), $headers));
        } catch (InvalidWebhookException $exception) {
            return ProblemDetails::response(400, $exception->getMessage());
        } catch (\LogicException) {
            return ProblemDetails::response(404, sprintf('Unknown payment gateway "%s".', $gateway));
        }

        return $this->json($result, $result['duplicate'] ? 200 : 202);
    }
}
