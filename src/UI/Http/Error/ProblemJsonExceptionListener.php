<?php

declare(strict_types=1);

namespace App\UI\Http\Error;

use App\Application\Tenancy\Exception\ReadOnlyTenantException;
use App\Application\Tenancy\Exception\StoreAccessDeniedException;
use App\Application\Tenancy\Exception\StoreNotFoundException;
use Doctrine\ORM\OptimisticLockException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Turns every exception on /api/* (or a JSON request) into problem+json, following the error
 * matrix in docs/diagrams/pages.html. Unknown errors become 500 with only a reference code;
 * internal messages are shown in debug mode only.
 *
 * Runs after the security listener (priority 1), which already answers 401 via the entry point.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 0)]
final readonly class ProblemJsonExceptionListener
{
    public function __construct(
        private LoggerInterface $logger,
        #[Autowire('%kernel.debug%')]
        private bool $debug,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!$this->wantsJson($request)) {
            return;
        }

        $exception = $event->getThrowable();
        $requestId = $this->requestId($request);
        $violations = $this->violations($exception);

        [$status, $detail, $headers] = match (true) {
            [] !== $violations => [422, 'Some fields are not filled in correctly.', []],
            $exception instanceof HttpExceptionInterface => [$exception->getStatusCode(), $exception->getMessage(), $exception->getHeaders()],
            $exception instanceof StoreAccessDeniedException, $exception instanceof ReadOnlyTenantException => [403, $exception->getMessage(), []],
            $exception instanceof StoreNotFoundException => [404, $exception->getMessage(), []],
            $exception instanceof OptimisticLockException => [409, 'This was changed in the meantime. Reload to see the latest version.', []],
            $exception instanceof \DomainException => [422, $exception->getMessage(), []],
            default => [500, 'Something went wrong on our side.', []],
        };

        if ('' === $detail || ($status >= 500 && !$this->debug)) {
            $detail = $status >= 500 ? 'Something went wrong on our side.' : 'The request could not be completed.';
        } elseif ($status >= 500) {
            $detail .= ' ['.$exception::class.': '.$exception->getMessage().']';
        }

        if ($status >= 500) {
            $this->logger->error('API error {requestId}: {message}', ['requestId' => $requestId, 'message' => $exception->getMessage(), 'exception' => $exception]);
        }

        /* @var array<string, string> $headers */
        $event->setResponse(ProblemDetails::response($status, $detail, $violations, $requestId, array_map('strval', $headers)));
    }

    private function wantsJson(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), '/api/')
            || 'json' === $request->getContentTypeFormat()
            || \in_array('application/json', $request->getAcceptableContentTypes(), true);
    }

    /**
     * @return list<array{propertyPath: string, message: string}>
     */
    private function violations(\Throwable $exception): array
    {
        for ($e = $exception; null !== $e; $e = $e->getPrevious()) {
            if ($e instanceof ValidationFailedException) {
                return $this->fromList($e->getViolations());
            }
        }

        return [];
    }

    /**
     * @return list<array{propertyPath: string, message: string}>
     */
    private function fromList(ConstraintViolationListInterface $list): array
    {
        $violations = [];
        foreach ($list as $violation) {
            $violations[] = ['propertyPath' => $violation->getPropertyPath(), 'message' => (string) $violation->getMessage()];
        }

        return $violations;
    }

    private function requestId(Request $request): ?string
    {
        $id = $request->attributes->get('_request_id');

        return \is_string($id) ? $id : null;
    }
}
