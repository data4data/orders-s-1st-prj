<?php

declare(strict_types=1);

namespace App\UI\Http\Payment;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The "payment provider page" of FakeGateway. Its URL is signed by the gateway, so amount, order
 * and return address cannot be tampered with. In Phase 6 the buttons also deliver the signed
 * webhook that moves the payment and the order on; for now they return to the shop.
 */
final class FakeGatewayController extends AbstractController
{
    public function __construct(private readonly UriSigner $uriSigner)
    {
    }

    #[Route('/fake-gateway/{reference}', name: 'fake_gateway_page', requirements: ['reference' => 'fake_[0-9a-f]+'], methods: ['GET', 'POST'])]
    public function page(Request $request, string $reference): Response
    {
        if (!$this->uriSigner->checkRequest($request)) {
            return $this->render('payment/fake_gateway.html.twig', ['valid' => false], new Response(status: 403));
        }

        $return = $request->query->getString('return');
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('fake_gateway', $request->getPayload()->getString('_token'))) {
                return $this->render('payment/fake_gateway.html.twig', ['valid' => false], new Response(status: 419));
            }
            $outcome = 'fail' === $request->getPayload()->getString('outcome') ? 'failed' : 'paid';

            return new RedirectResponse($return.(str_contains($return, '?') ? '&' : '?').'payment='.$outcome);
        }

        return $this->render('payment/fake_gateway.html.twig', [
            'valid' => true,
            'reference' => $reference,
            'amount' => $request->query->getInt('amount'),
            'currency' => $request->query->getString('currency'),
            'order' => $request->query->getString('order'),
            'return' => $return,
        ]);
    }
}
