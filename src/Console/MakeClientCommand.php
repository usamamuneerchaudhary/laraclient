<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

/**
 * Scaffolds a typed PHP client from an OpenAPI 3 document.
 *
 *     php artisan laraclient:make github --spec=storage/openapi/github.json
 *
 * The generated class is plain readable PHP that you own and can edit — not a
 * runtime reflection layer. Regenerating overwrites it, so keep customisations
 * in a subclass or pass --force knowingly.
 */
class MakeClientCommand extends Command
{
    protected $signature = 'laraclient:make
                            {connection            : The connection name in config/lara_client.php}
                            {--spec=               : Path or URL to an OpenAPI 3 document (json or yaml)}
                            {--namespace=App\\ApiClients : Namespace for the generated class}
                            {--class=              : Class name, defaults to StudlyConnectionClient}
                            {--path=               : Output path, defaults to app/ApiClients}
                            {--tag=*               : Only generate operations carrying these tags}
                            {--force               : Overwrite an existing file}';

    protected $description = 'Generate a typed API client class from an OpenAPI specification';

    public function handle(): int
    {
        $spec = $this->loadSpec();

        if ($spec === null) {
            return self::FAILURE;
        }

        $connection = (string) $this->argument('connection');
        $class = (string) ($this->option('class') ?: Str::studly($connection).'Client');
        $namespace = trim((string) $this->option('namespace'), '\\');

        $operations = $this->extractOperations($spec);

        if ($operations === []) {
            $this->components->error('No operations found in the specification.');

            return self::FAILURE;
        }

        $path = $this->outputPath($class);

        if (file_exists($path) && ! $this->option('force')) {
            $this->components->error("[{$path}] already exists. Pass --force to overwrite.");

            return self::FAILURE;
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0o755, true);
        }

        file_put_contents($path, $this->render($namespace, $class, $connection, $spec, $operations));

        $this->components->info("Generated [{$path}] with ".count($operations).' operation(s).');
        $this->newLine();
        $this->line('  Add the connection to <fg=yellow>config/lara_client.php</> if it is not there yet:');
        $this->newLine();
        $this->line($this->connectionStub($connection, $spec));

        return self::SUCCESS;
    }

    // --- Spec loading -----------------------------------------------------

    /** @return array<string, mixed>|null */
    protected function loadSpec(): ?array
    {
        $source = (string) $this->option('spec');

        if ($source === '') {
            $this->components->error('Pass --spec with a path or URL to an OpenAPI document.');

            return null;
        }

        $raw = str_starts_with($source, 'http')
            ? @file_get_contents($source)
            : (@file_get_contents(base_path($source)) ?: @file_get_contents($source));

        if ($raw === false || $raw === '') {
            $this->components->error("Could not read the specification at [{$source}].");

            return null;
        }

        if (str_ends_with($source, '.yaml') || str_ends_with($source, '.yml')) {
            if (! class_exists(Yaml::class)) {
                $this->components->error('Install symfony/yaml to read YAML specifications, or convert the spec to JSON.');

                return null;
            }

            return Yaml::parse($raw);
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            $this->components->error('The specification is not valid JSON.');

            return null;
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return list<array<string, mixed>>
     */
    protected function extractOperations(array $spec): array
    {
        $verbs = ['get', 'post', 'put', 'patch', 'delete', 'head', 'options'];
        $tags = array_filter((array) $this->option('tag'));
        $operations = [];
        $usedNames = [];

        foreach ($spec['paths'] ?? [] as $path => $item) {
            if (! is_array($item)) {
                continue;
            }

            $sharedParams = $item['parameters'] ?? [];

            foreach ($verbs as $verb) {
                if (! isset($item[$verb]) || ! is_array($item[$verb])) {
                    continue;
                }

                $operation = $item[$verb];

                if ($tags !== [] && array_intersect($tags, $operation['tags'] ?? []) === []) {
                    continue;
                }

                $name = $this->methodName($operation, $verb, (string) $path, $usedNames);
                $usedNames[] = $name;

                $params = array_merge($sharedParams, $operation['parameters'] ?? []);

                $operations[] = [
                    'method' => strtoupper($verb),
                    'path' => ltrim((string) $path, '/'),
                    'name' => $name,
                    'summary' => (string) ($operation['summary'] ?? $operation['description'] ?? ''),
                    'pathParams' => $this->pathParameters($params, (string) $path),
                    'queryParams' => $this->namedParameters($params, 'query'),
                    'hasBody' => isset($operation['requestBody']) && ! in_array($verb, ['get', 'head'], true),
                    'deprecated' => (bool) ($operation['deprecated'] ?? false),
                ];
            }
        }

        return $operations;
    }

    /**
     * @param  array<string, mixed>  $operation
     * @param  list<string>  $used
     */
    protected function methodName(array $operation, string $verb, string $path, array $used): string
    {
        $base = isset($operation['operationId'])
            ? Str::camel((string) $operation['operationId'])
            : Str::camel($verb.' '.str_replace(['{', '}', '/', '-'], [' ', '', ' ', ' '], $path));

        $base = preg_replace('/[^A-Za-z0-9_]/', '', $base) ?: 'call';

        if (is_numeric($base[0] ?? '')) {
            $base = 'op'.$base;
        }

        // operationIds are supposed to be unique and frequently are not.
        $name = $base;
        $suffix = 2;

        while (in_array($name, $used, true)) {
            $name = $base.$suffix++;
        }

        return $name;
    }

    /**
     * Path parameters become required, typed method arguments in the order they
     * appear in the URL template, so the signature reads like the endpoint.
     */
    /**
     * @param  list<array<string, mixed>>  $params
     * @return list<array<string, mixed>>
     */
    protected function pathParameters(array $params, string $path): array
    {
        preg_match_all('/\{([^{}\/]+)\}/', $path, $matches);

        $declared = [];

        foreach ($params as $param) {
            if (($param['in'] ?? null) === 'path') {
                $declared[$param['name']] = $param;
            }
        }

        $ordered = [];

        foreach ($matches[1] as $name) {
            $param = $declared[$name] ?? [];

            $ordered[] = [
                'name' => Str::camel($name),
                'raw' => $name,
                'type' => $this->phpType($param['schema'] ?? []),
                'description' => (string) ($param['description'] ?? ''),
            ];
        }

        return $ordered;
    }

    /**
     * @param  list<array<string, mixed>>  $params
     * @return list<string>
     */
    protected function namedParameters(array $params, string $in): array
    {
        $names = [];

        foreach ($params as $param) {
            if (($param['in'] ?? null) === $in) {
                $names[] = (string) $param['name'];
            }
        }

        return $names;
    }

    /** @param  array<string, mixed>  $schema */
    protected function phpType(array $schema): string
    {
        return match ($schema['type'] ?? 'string') {
            'integer' => 'int',
            'number' => 'float',
            'boolean' => 'bool',
            'array' => 'array',
            default => 'string',
        };
    }

    // --- Rendering --------------------------------------------------------

    /**
     * @param  array<string, mixed>  $spec
     * @param  list<array<string, mixed>>  $operations
     */
    protected function render(string $namespace, string $class, string $connection, array $spec, array $operations): string
    {
        $title = $spec['info']['title'] ?? $connection;
        $version = $spec['info']['version'] ?? 'unknown';

        $methods = implode("\n\n", array_map(
            fn (array $operation): string => $this->renderMethod($operation),
            $operations,
        ));

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        use Usamamuneerchaudhary\\LaraClient\\Facades\\LaraClient;
        use Usamamuneerchaudhary\\LaraClient\\PendingRequest;
        use Usamamuneerchaudhary\\LaraClient\\Response;

        /**
         * {$title} (spec version {$version})
         *
         * Generated by `php artisan laraclient:make {$connection}`.
         * Re-running the command overwrites this file — put anything you add by
         * hand in a subclass.
         */
        class {$class}
        {
            protected ?PendingRequest \$request = null;

            public function __construct(?PendingRequest \$request = null)
            {
                \$this->request = \$request;
            }

            /**
             * The underlying request, so callers can still reach the fluent API:
             *
             *     \$client->using(fn (\$r) => \$r->timeout(2))->listThings();
             */
            public function request(): PendingRequest
            {
                return \$this->request ??= LaraClient::connection('{$connection}');
            }

            public function using(callable \$callback): static
            {
                return new static(\$callback(\$this->request()));
            }

        {$methods}
        }

        PHP;
    }

    /** @param  array<string, mixed>  $operation */
    protected function renderMethod(array $operation): string
    {
        $args = [];
        $params = [];
        $notes = [];

        foreach ($operation['pathParams'] as $param) {
            $args[] = "{$param['type']} \${$param['name']}";
            $params[] = "'{$param['raw']}' => \${$param['name']}";

            $notes[] = rtrim("@param  {$param['type']}  \${$param['name']}  ".$param['description']);
        }

        $takesBody = $operation['hasBody'] && $operation['method'] !== 'DELETE';

        if ($takesBody) {
            $args[] = 'array $data = []';
        }

        // Body verbs carry their payload in $data, so there is no $query
        // argument to document for them.
        $takesQuery = ! $takesBody;

        if ($takesQuery) {
            $args[] = 'array $query = []';

            if ($operation['queryParams'] !== []) {
                $notes[] = '@param  array  $query  Supported keys: '
                    .implode(', ', array_slice($operation['queryParams'], 0, 12));
            }
        }

        if ($operation['deprecated']) {
            $notes[] = '@deprecated Marked deprecated in the specification.';
        }

        $verb = strtolower($operation['method']);
        $path = $operation['path'];
        $pathArgs = implode(', ', $params);

        // Path templates are passed through untouched: PendingRequest expands
        // {placeholders} and URL-encodes the values.
        if ($takesBody) {
            $call = $pathArgs === ''
                ? "return \$this->request()->{$verb}('{$path}', \$data);"
                : "return \$this->request()->withQuery([{$pathArgs}])->{$verb}('{$path}', \$data);";
        } else {
            $merged = $pathArgs === '' ? '$query' : "array_merge([{$pathArgs}], \$query)";
            $call = "return \$this->request()->{$verb}('{$path}', {$merged});";
        }

        $signature = implode(', ', $args);

        return $this->docblock($operation['summary'], $notes)
            ."    public function {$operation['name']}({$signature}): Response\n"
            ."    {\n        {$call}\n    }";
    }

    /**
     * @param  list<string>  $notes
     */
    protected function docblock(string $summary, array $notes): string
    {
        $summary = trim(str_replace('*/', '* /', $summary));

        $lines = [];

        if ($summary !== '') {
            $lines[] = $summary;
        }

        // Only separate the summary from the annotations when both exist.
        if ($summary !== '' && $notes !== []) {
            $lines[] = '';
        }

        foreach ($notes as $note) {
            $lines[] = $note;
        }

        if ($lines === []) {
            return '';
        }

        $body = implode("\n", array_map(
            static fn (string $line): string => rtrim('     * '.$line),
            $lines,
        ));

        return "    /**\n{$body}\n     */\n";
    }

    /** @param  array<string, mixed>  $spec */
    protected function connectionStub(string $connection, array $spec): string
    {
        $baseUri = $spec['servers'][0]['url'] ?? 'https://api.example.com';
        $env = Str::upper(Str::snake($connection));

        return <<<PHP
            '{$connection}' => [
                'base_uri' => env('{$env}_BASE_URI', '{$baseUri}'),
                'auth' => [
                    'driver' => 'bearer',
                    'token' => env('{$env}_TOKEN'),
                ],
            ],
        PHP;
    }

    protected function outputPath(string $class): string
    {
        $dir = (string) ($this->option('path') ?: 'app/ApiClients');

        return str_starts_with($dir, '/')
            ? "{$dir}/{$class}.php"
            : base_path("{$dir}/{$class}.php");
    }
}
