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
 * Not logged in on the admin host: API calls get a 401 problem+json (the SPA shows its
 * "session expired" dialog), page requests are redirected to the login page.
 */
final readonly class AdminAuthenticationEntryPoint implements AuthenticationEntryPointInterface
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

        return new RedirectResponse($this->urlGenerator->generate('admin_login_page'));
    }
}
