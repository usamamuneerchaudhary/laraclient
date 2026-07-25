# Upgrading to 2.x

LaraClient 2.0 requires **PHP 8.3+** and **Laravel 13**. If you are on Laravel 10–12, stay on 1.x
until you upgrade the framework.

Most of this guide is mechanical. The first section is not, please read it before deploying.

---

## 1. Rotate your API credentials

**This is a security fix, and installing the new version is not sufficient on its own.**

LaraClient 1.x logged requests like this:

```php
'request_payload' => json_encode($options),
```

`$options` contained `$options['headers']`, which contained the `Authorization` header. Every API key
and bearer token used through the package was written to the `laraclient_logs` table in plaintext,
and rendered in the log viewer, which shipped with its route middleware commented out and therefore
reachable by anyone who could guess `/laraclient/logs`.

If you ran 1.x in production:

1. **Rotate every credential** configured in `lara_client.php`. Assume they are compromised.
2. Check who could reach `/laraclient/logs` — web server logs will tell you whether anyone did.
3. Check whether your database backups contain the table. Rotating does not scrub the backups.

The included migration **drops** the `request_payload` column rather than migrating it. There is no
safe way to un-leak credentials already sitting in it, and carrying the column forward would carry
them with it. The migration is deliberately irreversible.

---

## 2. Run the migrations

```bash
php artisan migrate
```

`2026_07_25_000001_upgrade_laraclient_logs_table` detects a 1.x table and upgrades it in place:
renames `response_status` to `status`, adds `connection`, `duration_ms`, `attempt`, `cached`,
`request_headers`, `response_headers` and `exception`, adds the indexes the table never had, and
drops `request_payload`.

Prefer a clean start? Drop `laraclient_logs` and let the create migration build it fresh.

## 3. Remove the manual service provider registration

Package auto-discovery now handles it. Delete this line from `config/app.php` if it is there:

```php
\Usamamuneerchaudhary\LaraClient\LaraClientServiceProvider::class,
```

## 4. Republish the config

The config file was restructured around a `defaults` block and per-connection overrides.

```bash
php artisan vendor:publish --tag=laraclient-config --force
```

Back up your old file first and port your connections across:

```php
// 1.x
'api' => [
    'base_uri' => env('API_BASE_URI'),
    'api_key' => env('API_API_KEY'),
    'default_headers' => ['Accept' => 'application/json'],
    'timeout' => 30,
    'rate_limit' => ['limit' => 60, 'interval' => 60],
],

// 2.x
'api' => [
    'base_uri' => env('API_BASE_URI'),
    'auth' => ['driver' => 'bearer', 'token' => env('API_API_KEY')],
    'headers' => ['Accept' => 'application/json'],
    'timeout' => 30,
    'rate_limit' => ['enabled' => true, 'limit' => 60, 'window' => 60],
],
```

Key renames: `default_headers` → `headers`, `api_key` → `auth.token`,
`rate_limit.interval` → `rate_limit.window`. Throttling is now opt-in per connection via
`rate_limit.enabled`.

Not using bearer tokens? 1.x sent `Authorization: Bearer <api_key>` for everything, which is why
header-key APIs like RapidAPI never worked properly. Pick the driver that matches:

```php
'auth' => ['driver' => 'header', 'name' => 'X-RapidAPI-Key', 'value' => env('RAPIDAPI_KEY')],
```

## 5. Gate the dashboard

The log viewer is no longer public. Define who may see it:

```php
// AppServiceProvider::boot()
Gate::define('viewLaraClient', fn ($user) => $user->isAdmin());
```

Undefined, the gate allows `local` only. Set `dashboard.middleware` to match your auth stack, or
`dashboard.enabled => false` to remove the routes entirely.

## 6. Update your call sites

`new LaraClient('name')` still works but is deprecated. Move to the facade:

```php
// 1.x
$client = new LaraClient('weatherapi');
$response = $client->get('current.json', ['q' => 'london']);
$data = $response->getData();

// 2.x
$response = LaraClient::connection('weatherapi')->get('current.json', ['q' => 'london']);
$data = $response->json();
```

`getData()` and `getStatusCode()` remain as deprecated aliases through the 2.x line and are scheduled
for removal in 3.0. `getData()` returns an object; `json()` returns an array, so check any code that
uses `->` accessors on the result — or call `object()` for identical behaviour.

## 7. Handle the new exceptions

1.x threw `LaraClientApiClientException` for HTTP errors and crashed with a `TypeError` on connection
failures, because `ConnectException` does not extend `RequestException` and so escaped the catch
block entirely.

2.x returns a `Response` for 4xx/5xx by default — check `failed()`, or call `throwOnError()` — and
throws typed exceptions for everything else:

| Exception | When |
|---|---|
| `RequestException` | 4xx/5xx, after `->throw()` or `throwOnError()`. Carries `->response` |
| `ConnectionFailedException` | DNS failure, refused connection, timeout |
| `RateLimitExceededException` | Local throttle hit. Carries `->retryAfter` |
| `CircuitOpenException` | Upstream failing; requests short-circuited |
| `ConnectionNotConfiguredException` | No such connection in the config |

All extend `LaraClientException`, so a single catch still works during migration.

## 8. Schedule log pruning

The 1.x table grew without bound. Add:

```php
// routes/console.php
Schedule::command('laraclient:prune')->daily();
```

## 9. Verify

```bash
php artisan laraclient:check
```

Pings every configured connection and reports status, latency and authentication failures. Worth
adding to your deploy pipeline.

---

## Behaviour changes worth knowing

- **Retries are bounded.** 1.x recursed indefinitely on 429. 2.x caps attempts and backs off with
  jitter.
- **Unsafe verbs are not retried by default.** `POST` and `PATCH` are excluded unless you call
  `retryUnsafeMethods()`.
- **Rate limiting throws rather than sleeps.** 1.x called `sleep()` inside the request, holding a
  PHP process open. Catch `RateLimitExceededException` and release the job instead.
- **Throttle state is per connection.** 1.x used one global `api_rate_limit` cache key, so a 429
  from one API stalled calls to all the others.
- **Failed requests are logged.** 1.x only wrote a row on the success path.
- **The circuit breaker is on by default.** Disable per connection with
  `'circuit_breaker' => ['enabled' => false]` if you would rather keep hammering.
- **Caching is real.** 1.x advertised a cache layer in the README that was never implemented. It is
  off by default; enable it per connection or per call with `cacheFor()`.
