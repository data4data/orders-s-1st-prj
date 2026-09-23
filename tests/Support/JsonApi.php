<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * JSON API calls with the session CSRF token, for WebTestCase classes with a $client.
 */
trait JsonApi
{
    private string $csrfToken = '';

    /**
     * @param array<string, mixed>|null $body
     *
     * @return array<mixed>
     */
    private function api(string $method, string $url, ?array $body = null, int $expected = 200): array
    {
        if ('' === $this->csrfToken || 'GET' !== $method) {
            $this->refreshCsrfToken($url);
        }
        $this->client()->jsonRequest($method, $url, $body ?? [], ['HTTP_X_CSRF_TOKEN' => $this->csrfToken]);
        $content = (string) $this->client()->getResponse()->getContent();
        self::assertSame($expected, $this->client()->getResponse()->getStatusCode(), $content);

        return '' === $content ? [] : json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<mixed> $problem
     *
     * @return array<string, string> property path => message
     */
    private static function violations(array $problem): array
    {
        return array_column($problem['violations'] ?? [], 'message', 'propertyPath');
    }

    private function refreshCsrfToken(string $url): void
    {
        $origin = preg_replace('#^(https?://[^/]+).*$#', '$1', $url);
        $this->client()->request('GET', $origin.'/api/csrf-token');
        $this->csrfToken = json_decode((string) $this->client()->getResponse()->getContent(), true)['token'];
    }

    private function client(): KernelBrowser
    {
        return $this->client;
    }
}
