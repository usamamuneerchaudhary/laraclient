<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Console;

use Illuminate\Console\Command;
use Throwable;
use Usamamuneerchaudhary\LaraClient\LaraClientManager;
use Usamamuneerchaudhary\LaraClient\Response;
use Usamamuneerchaudhary\LaraClient\Support\ConnectionConfig;

/**
 * Pings every configured connection for any misconfiguration
 */
class CheckCommand extends Command
{
    protected $signature = 'laraclient:check
                            {connection?* : Connections to check, defaults to all}
                            {--path=       : Path to request, defaults to the connection root}
                            {--timeout=10  : Per-connection timeout in seconds}';

    protected $description = 'Check that every configured API connection is reachable and authenticated';

    public function handle(LaraClientManager $manager): int
    {
        $names = $this->argument('connection') ?: $manager->connectionNames();

        if ($names === []) {
            $this->components->warn('No connections are configured in config/lara_client.php.');

            return self::SUCCESS;
        }

        $rows = [];
        $failed = 0;

        foreach ($names as $name) {
            $result = $this->check($manager, (string) $name);

            $rows[] = $result['row'];

            if (! $result['ok']) {
                $failed++;
            }
        }

        $this->newLine();
        $this->table(['Connection', 'Base URI', 'Status', 'Time', 'Detail'], $rows);
        $this->newLine();

        if ($failed > 0) {
            $this->components->error("{$failed} of ".count($names).' connection(s) failed.');

            return self::FAILURE;
        }

        $this->components->info(count($names).' connection(s) reachable.');

        return self::SUCCESS;
    }

    /** @return array{ok: bool, row: list<string>} */
    protected function check(LaraClientManager $manager, string $name): array
    {
        $started = microtime(true);

        try {
            $request = $manager->connection($name)
                ->timeout((int) $this->option('timeout'))
                ->withoutRetrying()
                ->withoutCache()
                ->withoutCircuitBreaker()
                ->withoutLogging();

            $config = $request->config();
            $baseUri = $config->baseUri();
            [$path, $query] = $this->resolveHealthCheck($config);

            /** @var Response $response */
            $response = $request->get($path, $query);

            $elapsed = $this->elapsed($started);
            $expectsSuccess = $path !== '';
            $ok = $this->isHealthy($response->status(), $expectsSuccess);

            return [
                'ok' => $ok,
                'row' => [
                    $name,
                    $baseUri,
                    $this->badge($ok, (string) $response->status()),
                    $elapsed,
                    $this->detail($response, $ok, $expectsSuccess),
                ],
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'row' => [
                    $name,
                    $this->safeBaseUri($manager, $name),
                    $this->badge(false, 'ERR'),
                    $this->elapsed($started),
                    class_basename($e).': '.str($e->getMessage())->limit(60),
                ],
            ];
        }
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    protected function resolveHealthCheck(ConnectionConfig $config): array
    {
        $path = $this->option('path');

        if (is_string($path) && $path !== '') {
            return [$path, []];
        }

        $healthPath = $config->healthPath();

        if ($healthPath !== null) {
            return [$healthPath, $config->healthQuery()];
        }

        return ['', []];
    }

    protected function isHealthy(int $status, bool $expectsSuccess): bool
    {
        if (in_array($status, [401, 403], true)) {
            return false;
        }

        if ($expectsSuccess) {
            return $status >= 200 && $status < 300;
        }

        // A non-2xx at the root still proves DNS, TLS and routing work.
        return true;
    }

    protected function detail(Response $response, bool $ok, bool $expectsSuccess): string
    {
        if (in_array($response->status(), [401, 403], true)) {
            return 'Authentication rejected';
        }

        if ($ok) {
            return $response->reason();
        }

        if ($expectsSuccess) {
            return $response->reason() ?: 'Unexpected status';
        }

        return $response->reason();
    }

    protected function safeBaseUri(LaraClientManager $manager, string $name): string
    {
        try {
            return $manager->connection($name)->config()->baseUri();
        } catch (Throwable) {
            return '—';
        }
    }

    protected function badge(bool $ok, string $label): string
    {
        return $ok ? "<fg=green>{$label}</>" : "<fg=red>{$label}</>";
    }

    protected function elapsed(float $started): string
    {
        return round((microtime(true) - $started) * 1000).'ms';
    }
}
