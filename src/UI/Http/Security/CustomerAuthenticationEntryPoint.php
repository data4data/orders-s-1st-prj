<?php

declare(strict_types=1);

namespace App\UI\Http\Security;

use App\UI\Http\Error\ProblemDetails;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Not logged in on a shop: API calls get a 401 problem+json, pages go to the login page and come
 * back afterwards (the firewall remembers the target path).
 */
final readonly class CustomerAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            $requestId = $request->attributes->get('_request_id');

            return ProblemDetails::response(401, 'Please log in.', requestId: \is_string($requestId) ? $requestId : null);
        }

        return new RedirectResponse($this->urlGenerator->generate('customer_login'));
    }
}
