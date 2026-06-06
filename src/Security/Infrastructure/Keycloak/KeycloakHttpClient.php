<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Keycloak;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class KeycloakHttpClient implements KeycloakHttpClientInterface
{
    private ?string $adminToken = null;
    private \Closure $sleeper;

    /**
     * @param \Closure(int): void|null $sleeper injectable for tests; defaults to usleep
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly KeycloakRetryPolicy $retryPolicy,
        private readonly string $keycloakBaseUrl,
        private readonly string $keycloakRealm,
        private readonly string $keycloakClientId,
        private readonly string $keycloakClientSecret,
        private readonly LoggerInterface $logger,
        ?\Closure $sleeper = null,
    ) {
        $this->sleeper = $sleeper ?? static function (int $us): void { usleep($us); };
    }

    public function createUser(string $email, string $password): ResponseInterface
    {
        return $this->request('POST', "/admin/realms/{$this->keycloakRealm}/users", [
            'json' => [
                'email' => $email,
                'username' => $email,
                'emailVerified' => true,
                'enabled' => true,
                'credentials' => [[
                    'type' => 'password',
                    'value' => $password,
                    'temporary' => false,
                ]],
            ],
        ]);
    }

    public function deleteUser(string $keycloakId): void
    {
        $this->request('DELETE', "/admin/realms/{$this->keycloakRealm}/users/{$keycloakId}");
    }

    /**
     * @param array<string, mixed> $options
     */
    private function request(string $method, string $path, array $options = []): ResponseInterface
    {
        $attempt = 0;
        while (true) {
            ++$attempt;

            try {
                $options['auth_bearer'] = $this->ensureToken();
                $response = $this->httpClient->request($method, $this->keycloakBaseUrl.$path, $options);
                $status = $response->getStatusCode();
            } catch (\Throwable $e) {
                if ($attempt >= $this->retryPolicy->maxAttempts) {
                    throw new KeycloakUnavailableException('Keycloak is unreachable after retries', 0, $e);
                }
                ($this->sleeper)($this->exponentialDelayUs($attempt));
                continue;
            }

            if ($status >= 200 && $status < 300) {
                return $response;
            }

            if (401 === $status) {
                $this->adminToken = null;
                if ($attempt >= $this->retryPolicy->maxAttempts) {
                    throw new KeycloakUnavailableException('Keycloak authentication failed after retries');
                }
                continue;
            }

            if (429 === $status) {
                if ($attempt >= $this->retryPolicy->maxAttempts) {
                    throw new KeycloakUnavailableException('Keycloak rate limit exceeded after retries');
                }
                $retryAfterSeconds = (int) ($response->getHeaders(false)['retry-after'][0] ?? 1);
                ($this->sleeper)($retryAfterSeconds * 1_000_000);
                continue;
            }

            if ($status >= 500) {
                if ($attempt >= $this->retryPolicy->maxAttempts) {
                    throw new KeycloakUnavailableException("Keycloak server error {$status} after retries");
                }
                ($this->sleeper)($this->exponentialDelayUs($attempt));
                continue;
            }

            throw new KeycloakUnavailableException("Keycloak non-retriable error: HTTP {$status}");
        }
    }

    private function ensureToken(): string
    {
        if (null !== $this->adminToken) {
            return $this->adminToken;
        }

        $this->logger->debug('Fetching Keycloak admin token', ['realm' => $this->keycloakRealm]);

        $response = $this->httpClient->request(
            'POST',
            "{$this->keycloakBaseUrl}/realms/{$this->keycloakRealm}/protocol/openid-connect/token",
            [
                'body' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->keycloakClientId,
                    'client_secret' => $this->keycloakClientSecret,
                ],
            ],
        );

        $token = $response->toArray()['access_token'];
        $this->adminToken = \is_string($token) ? $token : '';

        return $this->adminToken;
    }

    private function exponentialDelayUs(int $attempt): int
    {
        $jitterMs = random_int(0, 100);

        return ($this->retryPolicy->baseDelayMs * (2 ** ($attempt - 1)) + $jitterMs) * 1_000;
    }
}
