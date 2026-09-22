<?php

declare(strict_types=1);

namespace App\UI\Http\Api;

use App\Application\Bus\CommandBusInterface;
use App\Application\Content\ContactInput;
use App\Application\Content\SubmitContactMessage;
use App\UI\Http\Security\RateLimitGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contact form, sent with AJAX from the Bootstrap page. 3 messages per 10 minutes per IP.
 */
final class ContactController extends AbstractController
{
    public function __construct(private readonly CommandBusInterface $commandBus)
    {
    }

    #[Route('/api/contact', name: 'api_contact', methods: ['POST'])]
    public function send(Request $request, #[MapRequestPayload] ContactInput $input, #[Target('contact_form.limiter')] RateLimiterFactoryInterface $limiter): Response
    {
        RateLimitGuard::consume($limiter, $request->getClientIp() ?? 'unknown');
        $this->commandBus->dispatch(new SubmitContactMessage($input));

        return new Response(status: 204);
    }
}
