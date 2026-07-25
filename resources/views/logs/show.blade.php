@extends('laraclient::layout')

@section('title', $log->method.' '.$log->shortEndpoint())

@section('content')
    <header class="masthead">
        <h1 class="wordmark">{{ $log->method }} <span>{{ $log->shortEndpoint() }}</span></h1>
        <a class="back" href="{{ route('laraclient.logs.index', ['connection' => $log->connection]) }}">← all requests</a>
    </header>

    <dl class="detail-grid">
        <div>
            <dt>Status</dt>
            <dd class="status {{ $log->statusClass() }}">{{ $log->status ?? 'failed' }}</dd>
        </div>
        <div>
            <dt>Duration</dt>
            <dd>{{ $log->duration_ms !== null ? $log->duration_ms.'ms' : '—' }}</dd>
        </div>
        <div>
            <dt>Connection</dt>
            <dd>{{ $log->connection }}</dd>
        </div>
        <div>
            <dt>Attempt</dt>
            <dd>{{ $log->attempt }}</dd>
        </div>
        <div>
            <dt>Served from cache</dt>
            <dd>{{ $log->cached ? 'yes' : 'no' }}</dd>
        </div>
        <div>
            <dt>Recorded</dt>
            <dd>{{ $log->created_at?->format('Y-m-d H:i:s') }}</dd>
        </div>
    </dl>

    <p class="note">
        Credentials are masked before anything is written here. Widen the mask by
        adding header names or body keys to <code>redact</code> in config/lara_client.php.
    </p>

    @if ($log->exception)
        <section class="payload">
            <h2>Exception</h2>
            <pre>{{ $log->exception }}</pre>
        </section>
    @endif

    <section class="payload">
        <h2>Request headers</h2>
        <pre>{{ json_encode($log->request_headers ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </section>

    @if ($log->request_body)
        <section class="payload">
            <h2>Request body</h2>
            <pre>{{ $log->request_body }}</pre>
        </section>
    @endif

    <section class="payload">
        <h2>Response headers</h2>
        <pre>{{ json_encode($log->response_headers ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </section>

    @if ($log->response_body)
        <section class="payload">
            <h2>Response body</h2>
            <pre>{{ $log->response_body }}</pre>
        </section>
    @endif
@endsection
