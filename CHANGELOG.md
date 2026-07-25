# Changelog

## 2.0.0

Requires PHP 8.3+ and Laravel 13. See [UPGRADE.md](UPGRADE.md) — **the security section needs action
beyond installing this release.**

### Security

- Credentials are redacted before anything is written to the logs table, a fixture or the dashboard.
  1.x wrote `json_encode($options)` directly, storing every `Authorization` header in plaintext.
  Redaction covers headers, nested body keys (including inside lists) and query-string secrets.
- The log dashboard is now behind a `viewLaraClient` gate that denies everyone outside `local`
  by default. Auth middleware is optional via `dashboard.middleware`. 1.x shipped the route with
  its middleware commented out.
- The upgrade migration **drops** the 1.x `request_payload` column rather than migrating it.

### Fixed

- Connection failures no longer crash. `ConnectException` does not extend `RequestException`, so 1.x
  missed DNS failures and timeouts entirely and raised a `TypeError` on `null->getStatusCode()`.
  These now surface as `ConnectionFailedException`.
- Retries are bounded. 1.x recursed into itself on every 429 with no attempt counter, looping until
  the process died. Backoff is exponential with full jitter and honours `Retry-After` in both
  integer and HTTP-date form.
- Rate limiting is scoped per connection. 1.x used a single global `api_rate_limit` cache key, so a
  429 from one API stalled calls to every other API in the app.
- Rate limiting throws `RateLimitExceededException` instead of calling `sleep()` inside the request
  and holding a PHP process open.
- Failed requests are logged. 1.x only wrote a log row on the success path, so the calls worth
  debugging left no trace.
- `Response::json()` decodes once and memoises. 1.x re-ran `json_decode` on every `getData()` call.
- Path placeholders containing hyphens or dots are expanded correctly.
- Base URI joining no longer produces double slashes.

### Added

- `LaraClientManager` and a `LaraClient` facade, replacing `new LaraClient('name')`.
- Fluent, clone-on-write `PendingRequest`: timeouts, retry policy, caching, throttling, idempotency
  keys, body format, streaming to disk.
- Five auth drivers: `bearer`, `basic`, `header`, `query`, `oauth2`, plus `AuthFactory::extend()`
  for custom signing. OAuth2 client credentials are cached and refreshed automatically, including on
  a 401.
- Response caching with `Cache-Control` support, ETag revalidation and tagged flushing. 1.x
  advertised this in the README but never implemented it.
- Circuit breaker with a half-open probe, and `CircuitOpened` / `CircuitClosed` events.
- `Pool` for genuinely concurrent requests over a shared curl multi handler.
- `Paginator` over `LazyCollection` with `page`, `offset`, `cursor` and `link_header` strategies.
- Testing fake with glob patterns, response sequences, simulated connection failures and
  `assertSent` / `assertNotSent` / `assertSentCount` / `assertSentInOrder`.
- Record-and-replay fixtures for hermetic test suites.
- `RequestSending`, `ResponseReceived` and `RequestFailed` events, plus `beforeSending()` and
  `afterResponse()` global hooks.
- Pulse integration when `laravel/pulse` is installed.
- `laraclient:check`, `laraclient:prune` and `laraclient:make` (OpenAPI 3 client scaffolding).
- Rebuilt dashboard with per-connection filtering, p50/p95 latency and a request trace strip.
- Package auto-discovery: the manual service provider registration step is gone.
- Indexes on the logs table, plus `connection`, `duration_ms`, `attempt` and `cached` columns.

### Deprecated

- `Response::getData()` and `Response::getStatusCode()`. Use `json()` / `object()` and `status()`.
  Removal planned for 3.0.

## 1.x

See the git history.
