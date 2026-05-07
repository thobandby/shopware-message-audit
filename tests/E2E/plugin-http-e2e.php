<?php

declare(strict_types=1);

final class PluginHttpE2ETest
{
    private string $baseUrl;
    private string $username;
    private string $password;
    private string $accessToken;

    public function __construct(string $baseUrl, string $username, string $password)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->username = $username;
        $this->password = $password;
        $this->accessToken = '';
    }

    public function run(): void
    {
        $this->log('Authenticating against Shopware admin API');
        $this->accessToken = $this->authenticate();

        $this->log('Loading paginated message list');
        $listResponse = $this->requestJson(
            'GET',
            '/api/_action/mh/messages?limit=10&page=1&topicGroup=System'
        );
        $messages = $this->assertListResponse($listResponse, 1, 10);
        $this->assertSame(10, \count($messages), 'message page size');

        $this->log('Loading second page');
        $secondPageResponse = $this->requestJson(
            'GET',
            '/api/_action/mh/messages?limit=10&page=2&topicGroup=System'
        );
        $secondPageMessages = $this->assertListResponse($secondPageResponse, 2, 10);
        $this->assertNotSame(
            $messages[0]['id'] ?? null,
            $secondPageMessages[0]['id'] ?? null,
            'page 1 and page 2 first message'
        );

        $this->log('Loading failed messages');
        $failedMessages = $this->assertListResponse(
            $this->requestJson('GET', '/api/_action/mh/messages?status=failed'),
            1,
            25
        );

        $this->log('Loading payment-related messages');
        $paymentMessages = $this->assertListResponse(
            $this->requestJson('GET', '/api/_action/mh/messages?topicGroup=Zahlungen'),
            1,
            25
        );
        foreach ($paymentMessages as $paymentMessage) {
            $this->assertSame('Zahlungen', $paymentMessage['topic_group'] ?? null, 'payment topic group');
        }

        $message = $failedMessages[0] ?? $messages[0] ?? null;
        if (!\is_array($message) || !isset($message['id']) || !\is_string($message['id'])) {
            throw new \RuntimeException('No message record returned by the plugin API.');
        }

        $messageId = $message['id'];
        $messageClass = (string) ($message['message_class'] ?? '');
        $messageCreatedAt = (string) ($message['created_at'] ?? '');

        $this->log('Filtering by message id');
        $messageIdMatches = $this->assertListResponse(
            $this->requestJson('GET', '/api/_action/mh/messages?query=' . rawurlencode($messageId)),
            1,
            25
        );
        $this->assertContains($messageId, array_column($messageIdMatches, 'id'), 'message id filter');

        if ($messageClass !== '') {
            $this->log('Filtering by message class fragment');
            $classFragment = $this->extractClassFragment($messageClass);
            $classMatches = $this->assertListResponse(
                $this->requestJson('GET', '/api/_action/mh/messages?query=' . rawurlencode($classFragment)),
                1,
                25
            );
            $this->assertContains($messageId, array_column($classMatches, 'id'), 'message class filter');
        }

        if ($messageCreatedAt !== '') {
            $createdDate = substr($messageCreatedAt, 0, 10);
            $this->log('Filtering by created date');
            $dateMatches = $this->assertListResponse(
                $this->requestJson(
                    'GET',
                    '/api/_action/mh/messages?createdFrom=' . rawurlencode($createdDate) . '&createdTo=' . rawurlencode($createdDate)
                ),
                1,
                25
            );
            $this->assertContains($messageId, array_column($dateMatches, 'id'), 'created date filter');
        }

        $this->log('Inspecting message ' . $messageId);
        $detail = $this->requestJson('GET', '/api/_action/mh/messages/' . rawurlencode($messageId));

        $this->assertArrayHasKey($detail, 'message', 'detail response');
        $this->assertArrayHasKey($detail, 'transitions', 'detail response');
        $this->assertArrayHasKey($detail, 'failures', 'detail response');
        $this->assertArrayHasKey($detail, 'actions', 'detail response');
        $this->assertArrayHasKey($detail['message'], 'topic_group', 'detail message');
        $this->assertArrayHasKey($detail['message'], 'business_summary', 'detail message');
        $this->assertArrayHasKey($detail['message'], 'business_impact', 'detail message');
        $this->assertArrayHasKey($detail['message'], 'status_label', 'detail message');

        $this->log('Posting quarantine action');
        $this->requestJson(
            'POST',
            '/api/_action/mh/messages/' . rawurlencode($messageId) . '/quarantine',
            ['reason' => 'local e2e quarantine']
        );

        $this->log('Posting dismiss action');
        $this->requestJson(
            'POST',
            '/api/_action/mh/messages/' . rawurlencode($messageId) . '/dismiss',
            ['reason' => 'local e2e dismiss']
        );

        $this->log('Posting retry action');
        $retry = $this->requestJson(
            'POST',
            '/api/_action/mh/messages/' . rawurlencode($messageId) . '/retry',
            ['reason' => 'local e2e retry']
        );
        $this->assertSame('ok', $retry['status'] ?? null, 'retry status');
        $this->assertNonEmptyString($retry['replayedMessageId'] ?? null, 'replayedMessageId');

        $this->log('Re-loading detail after operator actions');
        $afterActions = $this->requestJson('GET', '/api/_action/mh/messages/' . rawurlencode($messageId));
        $actions = $afterActions['actions'] ?? null;
        if (!\is_array($actions)) {
            throw new \RuntimeException('Expected actions array after operator actions.');
        }

        $actionNames = array_map(
            static fn (array $action): string => (string) ($action['action'] ?? ''),
            array_filter($actions, 'is_array')
        );

        $this->assertContains('quarantine', $actionNames, 'actions');
        $this->assertContains('dismiss', $actionNames, 'actions');
        $this->assertContains('retry_now', $actionNames, 'actions');

        $this->log('Checking metrics endpoint');
        $metrics = $this->requestJson('GET', '/api/_action/mh/metrics');
        $this->assertArrayHasKey($metrics, 'total', 'metrics');
        if (!\is_int($metrics['total']) || $metrics['total'] < 1) {
            throw new \RuntimeException('Expected metrics.total to be >= 1.');
        }

        $this->log('Checking cleanup endpoint with no-op retention');
        $cleanup = $this->requestJson(
            'GET',
            '/api/_action/mh/messages?cleanupDays=365'
        );
        $this->assertSame('ok', $cleanup['status'] ?? null, 'cleanup status');
        $this->assertArrayHasKey($cleanup, 'deleted', 'cleanup response');
        $this->assertArrayHasKey($cleanup['deleted'], 'messages', 'cleanup deleted payload');

        $this->log('E2E test completed successfully');
    }

    private function authenticate(): string
    {
        $response = $this->requestRaw(
            'POST',
            '/api/oauth/token',
            http_build_query([
                'grant_type' => 'password',
                'client_id' => 'administration',
                'scopes' => 'write',
                'username' => $this->username,
                'password' => $this->password,
            ]),
            ['Content-Type: application/x-www-form-urlencoded']
        );

        $payload = $this->decodeJson($response['body'], '/api/oauth/token');
        $token = $payload['access_token'] ?? null;
        $this->assertNonEmptyString($token, 'access_token');

        return $token;
    }

    /**
     * @return array<string, mixed>
     */
    private function requestJson(string $method, string $path, ?array $formData = null): array
    {
        $headers = ['Authorization: Bearer ' . $this->accessToken];
        $body = null;

        if ($formData !== null) {
            $body = http_build_query($formData);
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        $response = $this->requestRaw($method, $path, $body, $headers);

        return $this->decodeJson($response['body'], $path);
    }

    /**
     * @param list<string> $headers
     *
     * @return array{status:int, body:string}
     */
    private function requestRaw(string $method, string $path, ?string $body = null, array $headers = []): array
    {
        $url = str_starts_with($path, 'http') ? $path : $this->baseUrl . $path;
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", array_merge(['Accept: application/json'], $headers)),
                'content' => $body ?? '',
                'ignore_errors' => true,
                'timeout' => 30,
            ],
        ]);

        $responseBody = @file_get_contents($url, false, $context);
        if ($responseBody === false) {
            $error = error_get_last();
            throw new \RuntimeException('HTTP request failed for ' . $url . ': ' . ($error['message'] ?? 'unknown error'));
        }

        $statusLine = $http_response_header[0] ?? 'HTTP/1.1 500';
        if (!preg_match('/\s(\d{3})\s/', $statusLine, $matches)) {
            throw new \RuntimeException('Could not parse HTTP status line: ' . $statusLine);
        }

        $status = (int) $matches[1];
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException(
                \sprintf('Unexpected HTTP %d for %s %s: %s', $status, $method, $url, $responseBody)
            );
        }

        return ['status' => $status, 'body' => $responseBody];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $body, string $path): array
    {
        $decoded = json_decode($body, true);

        if (!\is_array($decoded)) {
            throw new \RuntimeException('Expected JSON object response for ' . $path . '.');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array<string, mixed>>
     */
    private function assertListResponse(array $payload, int $expectedPage, int $expectedLimit): array
    {
        $this->assertSame($expectedPage, $payload['page'] ?? null, 'list page');
        $this->assertSame($expectedLimit, $payload['limit'] ?? null, 'list limit');
        $this->assertArrayHasKey($payload, 'total', 'list response');

        $data = $payload['data'] ?? null;
        if (!\is_array($data)) {
            throw new \RuntimeException('Expected "data" array in list response.');
        }

        return array_values(array_filter($data, 'is_array'));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assertArrayHasKey(array $payload, string $key, string $context): void
    {
        if (!\array_key_exists($key, $payload)) {
            throw new \RuntimeException(\sprintf('Missing key "%s" in %s.', $key, $context));
        }
    }

    private function assertContains(string $expected, array $values, string $context): void
    {
        if (!\in_array($expected, $values, true)) {
            throw new \RuntimeException(\sprintf('Expected "%s" in %s.', $expected, $context));
        }
    }

    private function assertNonEmptyString(mixed $value, string $context): void
    {
        if (!\is_string($value) || $value === '') {
            throw new \RuntimeException('Expected non-empty string for ' . $context . '.');
        }
    }

    private function assertSame(mixed $expected, mixed $actual, string $context): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException(
                \sprintf(
                    'Expected %s for %s, got %s.',
                    var_export($expected, true),
                    $context,
                    var_export($actual, true)
                )
            );
        }
    }

    private function assertNotSame(mixed $expected, mixed $actual, string $context): void
    {
        if ($expected === $actual) {
            throw new \RuntimeException(
                \sprintf(
                    'Expected different values for %s, both were %s.',
                    $context,
                    var_export($actual, true)
                )
            );
        }
    }

    private function extractClassFragment(string $messageClass): string
    {
        $segments = explode('\\', $messageClass);

        return $segments[\count($segments) - 1] ?? $messageClass;
    }

    private function log(string $message): void
    {
        fwrite(STDOUT, '[e2e] ' . $message . PHP_EOL);
    }
}

/**
 * @return array{base-url:string, username:string, password:string}
 */
function parseArguments(array $argv): array
{
    $options = [
        'base-url' => 'http://127.0.0.1:18000',
        'username' => 'admin',
        'password' => 'shopware',
    ];

    foreach (\array_slice($argv, 1) as $argument) {
        if (!str_starts_with($argument, '--') || !str_contains($argument, '=')) {
            continue;
        }

        [$name, $value] = explode('=', substr($argument, 2), 2);
        if (\array_key_exists($name, $options)) {
            $options[$name] = $value;
        }
    }

    return $options;
}

$arguments = parseArguments($argv);
$test = new PluginHttpE2ETest(
    $arguments['base-url'],
    $arguments['username'],
    $arguments['password']
);
$test->run();
