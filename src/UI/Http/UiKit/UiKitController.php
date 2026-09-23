<?php

declare(strict_types=1);

namespace App\UI\Http\UiKit;

use App\UI\Http\Error\CsrfTokenExpiredHttpException;
use App\UI\Http\Error\ProblemDetails;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * UI kit (dev only): demo pages and endpoints that show every UI standard from
 * docs/diagrams/pages.html (toasts, form errors, dialogs, loading and error handling) in both
 * frontends. Not available in test or production.
 */
final class UiKitController extends AbstractController
{
    /** Status codes the error-handling demo can trigger. */
    private const DEMO_STATUSES = [400, 401, 403, 404, 405, 409, 413, 419, 429, 500, 502, 503, 504];

    #[Route('/ui-kit', name: 'ui_kit_bootstrap', methods: ['GET', 'POST'], env: 'dev')]
    public function bootstrap(Request $request): Response
    {
        $form = $this->createForm(UiKitContactType::class, new UiKitContactData());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->addFlash('success', 'ui_kit.form.sent');

            return $this->redirectToRoute('ui_kit_bootstrap');
        }

        return $this->render('ui_kit/bootstrap.html.twig', [
            'form' => $form,
            'statuses' => self::DEMO_STATUSES,
        ], new Response(status: $form->isSubmitted() ? 422 : 200));
    }

    #[Route('/ui-kit/vue', name: 'ui_kit_vue', methods: ['GET'], env: 'dev')]
    public function vue(): Response
    {
        return $this->render('ui_kit/vue.html.twig', ['statuses' => self::DEMO_STATUSES]);
    }

    #[Route('/api/ui-kit/contact', name: 'api_ui_kit_contact', methods: ['POST'], env: 'dev')]
    public function contact(#[MapRequestPayload] UiKitContactData $data): JsonResponse
    {
        return $this->json(['message' => sprintf('Thanks %s, your message was sent.', $data->name)]);
    }

    #[Route('/api/ui-kit/status/{code<\d{3}>}', name: 'api_ui_kit_status', methods: ['GET', 'POST'], env: 'dev')]
    public function status(int $code): JsonResponse
    {
        return match ($code) {
            401 => ProblemDetails::response(401, 'Please log in.'),
            419 => throw new CsrfTokenExpiredHttpException(),
            429 => throw new TooManyRequestsHttpException(30, 'Too many requests. Please wait a moment and try again.'),
            500 => throw new \RuntimeException('Simulated server failure (UI kit).'),
            default => \in_array($code, self::DEMO_STATUSES, true)
                ? throw new HttpException($code, sprintf('Simulated %d response (UI kit).', $code)) : $this->json(['ok' => true]),
        };
    }

    /** A real limiter: 3 calls per minute, then 429 with Retry-After. */
    #[Route('/api/ui-kit/limited', name: 'api_ui_kit_limited', methods: ['POST'], env: 'dev')]
    public function limited(Request $request, #[Target('ui_kit_demo.limiter')] RateLimiterFactoryInterface $limiter): JsonResponse
    {
        $limit = $limiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(max(1, $limit->getRetryAfter()->getTimestamp() - time()), 'Too many attempts. Please wait before trying again.');
        }

        return $this->json(['message' => sprintf('Accepted. %d attempts left this minute.', $limit->getRemainingTokens())]);
    }

    /** Slow list for the skeleton demo; ?empty=1 returns nothing for the empty state. */
    #[Route('/api/ui-kit/items', name: 'api_ui_kit_items', methods: ['GET'], env: 'dev')]
    public function items(Request $request): JsonResponse
    {
        usleep(1_200_000);

        return $this->json($request->query->getBoolean('empty') ? [] : [
            ['name' => "MyOil's Synth Pro 5W-30", 'detail' => '5 L · €49.95'],
            ['name' => "MyOil's Longlife 0W-20", 'detail' => '1 L · €14.50'],
            ['name' => "MyOil's Coolant G12++", 'detail' => '5 L · €8.95'],
        ]);
    }

    /** Never answers within the 15 s client timeout. */
    #[Route('/api/ui-kit/slow', name: 'api_ui_kit_slow', methods: ['GET'], env: 'dev')]
    public function slow(): JsonResponse
    {
        sleep(20);

        return $this->json(['message' => 'Too late.']);
    }
}
