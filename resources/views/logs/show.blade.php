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
        @include('laraclient::partials.payload-section', [
            'title' => 'Exception',
            'id' => 'exception',
            'content' => $log->exception,
        ])
    @endif

    @include('laraclient::partials.payload-section', [
        'title' => 'Request headers',
        'id' => 'request-headers',
        'content' => json_encode($log->request_headers ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    ])

    @if ($log->request_body)
        @include('laraclient::partials.payload-section', [
            'title' => 'Request body',
            'id' => 'request-body',
            'content' => $log->request_body,
        ])
    @endif

    @include('laraclient::partials.payload-section', [
        'title' => 'Response headers',
        'id' => 'response-headers',
        'content' => json_encode($log->response_headers ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    ])

    @if ($log->response_body)
        @include('laraclient::partials.payload-section', [
            'title' => 'Response body',
            'id' => 'response-body',
            'content' => $log->response_body,
        ])
    @endif
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-copy-target]').forEach((button) => {
            button.addEventListener('click', async () => {
                const target = document.getElementById(button.dataset.copyTarget);

                if (! target) {
                    return;
                }

                const label = button.querySelector('.copy-btn-label');

                try {
                    await navigator.clipboard.writeText(target.textContent);
                } catch {
                    const selection = window.getSelection();
                    const range = document.createRange();

                    range.selectNodeContents(target);
                    selection.removeAllRanges();
                    selection.addRange(range);

                    if (! document.execCommand('copy')) {
                        selection.removeAllRanges();

                        return;
                    }

                    selection.removeAllRanges();
                }

                button.classList.add('is-copied');
                label.textContent = 'Copied';

                window.setTimeout(() => {
                    button.classList.remove('is-copied');
                    label.textContent = 'Copy';
                }, 2000);
            });
        });
    </script>
@endpush
