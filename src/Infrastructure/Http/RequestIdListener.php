<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Gives every request a short reference code (e.g. "7F3A-91C2"): sent back as X-Request-Id,
 * added to every log line, and shown on error pages and error toasts so support can find it.
 */
#[AsMonologProcessor]
final class RequestIdListener implements ResetInterface
{
    public const ATTRIBUTE = '_request_id';
    public const HEADER = 'X-Request-Id';

    private ?string $requestId = null;

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 512)]
    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $this->requestId = strtoupper(bin2hex(random_bytes(2)).'-'.bin2hex(random_bytes(2)));
        $event->getRequest()->attributes->set(self::ATTRIBUTE, $this->requestId);
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -512)]
    public function onResponse(ResponseEvent $event): void
    {
        if ($event->isMainRequest() && null !== $this->requestId) {
            $event->getResponse()->headers->set(self::HEADER, $this->requestId);
        }
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        if (null !== $this->requestId) {
            $record->extra['request_id'] = $this->requestId;
        }

        return $record;
    }

    public function reset(): void
    {
        $this->requestId = null;
    }
}
