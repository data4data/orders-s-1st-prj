<?php

declare(strict_types=1);

namespace App\Infrastructure\Payment;

use App\Application\Payment\PaymentEventType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The "payment provider page" of FakeGateway (part of the fake provider, hence Infrastructure). Its URL is signed by the gateway, so amount, order
 * and addresses cannot be tampered with. Pay / Fail send a signed webhook to the shop, exactly
 * like a real provider, then return the customer to the confirmation page.
 */
final class FakeGatewayController extends AbstractController
{
    public function __construct(
        private readonly UriSigner $uriSigner,
        private readonly FakeGateway $gateway,
        private readonly HttpKernelInterface $kernel,
    ) {
    }

    #[Route('/fake-gateway/{reference}', name: 'fake_gateway_page', requirements: ['reference' => 'fake_[0-9a-f]+'], methods: ['GET', 'POST'])]
    public function page(Request $request, string $reference): Response
    {
        if (!$this->uriSigner->checkRequest($request)) {
            return $this->render('payment/fake_gateway.html.twig', ['valid' => false], new Response(status: 403));
        }

        $return = $request->query->getString('return');
        $amount = $request->query->getInt('amount');
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('fake_gateway', $request->getPayload()->getString('_token'))) {
                return $this->render('payment/fake_gateway.html.twig', ['valid' => false], new Response(status: 419));
            }
            $failed = 'fail' === $request->getPayload()->getString('outcome');
            $this->deliverWebhook($request->query->getString('webhook'), $reference, $failed ? PaymentEventType::Failed : PaymentEventType::Captured, $amount);

            return new RedirectResponse($return.(str_contains($return, '?') ? '&' : '?').'payment='.($failed ? 'failed' : 'paid'));
        }

        return $this->render('payment/fake_gateway.html.twig', [
            'valid' => true,
            'reference' => $reference,
            'amount' => $amount,
            'currency' => $request->query->getString('currency'),
            'order' => $request->query->getString('order'),
            'return' => $return,
        ]);
    }

    /**
     * A real provider calls the webhook over HTTP. The fake one hands it to the kernel directly: PHP's
     * built-in server handles one request at a time and would wait for itself.
     */
    private function deliverWebhook(string $url, string $reference, PaymentEventType $type, int $amount): void
    {
        $payload = $this->gateway->webhookPayload($reference, $type, $amount);
        $webhook = Request::create($url, 'POST', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_'.strtoupper(str_replace('-', '_', FakeGateway::SIGNATURE_HEADER)) => $this->gateway->sign($payload),
        ], content: $payload);
        $this->kernel->handle($webhook, HttpKernelInterface::SUB_REQUEST, false);
    }
}
