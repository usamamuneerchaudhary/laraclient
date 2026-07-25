@extends('laraclient::layout')

@section('title', 'Outbound requests')

@section('content')
    @php
        $slowest = max(1, $trace->max('duration_ms') ?? 1);
    @endphp

    <header class="masthead">
        <h1 class="wordmark">laraclient <span>/ outbound requests</span></h1>
        <p class="window-label">Last 24 hours</p>
    </header>

    {{-- The trace strip: one bar per call, height is latency, colour is outcome.
         A cluster of red bars is a failing window; a wall of tall bars is a slow
         upstream. Both are visible before you read a single row. --}}
    <section class="trace" aria-label="Recent request trace">
        <div class="trace-head">
            <span class="trace-title">Recent calls · height is latency</span>
            <span class="trace-title">{{ $trace->count() }} shown</span>
        </div>

        @if ($trace->isEmpty())
            <p class="trace-empty">Nothing recorded yet.</p>
        @else
            <div class="trace-bars">
                @foreach ($trace as $point)
                    @php
                        $height = max(3, (int) round((($point->duration_ms ?? 0) / $slowest) * 100));
                        $class = $point->statusClass();
                    @endphp
                    <a class="bar {{ $class }}"
                       style="height: {{ $height }}%"
                       href="{{ route('laraclient.logs.show', $point->id) }}"
                       title="{{ $point->method }} {{ $point->shortEndpoint() }} — {{ $point->status ?? 'failed' }} in {{ $point->duration_ms ?? 0 }}ms"
                       aria-label="{{ $point->method }} {{ $point->shortEndpoint() }}, status {{ $point->status ?? 'failed' }}, {{ $point->duration_ms ?? 0 }} milliseconds"></a>
                @endforeach
            </div>
            <div class="trace-axis">
                <span>{{ $trace->first()?->created_at?->diffForHumans() }}</span>
                <span>{{ $slowest }}ms peak</span>
                <span>now</span>
            </div>
        @endif
    </section>

    <dl class="stats">
        <div class="stat">
            <dt>Calls</dt>
            <dd>{{ number_format($stats['total']) }}</dd>
        </div>
        <div class="stat {{ $stats['error_rate'] > 0 ? 'is-bad' : '' }}">
            <dt>Error rate</dt>
            <dd>{{ $stats['error_rate'] }}<small>%</small></dd>
        </div>
        <div class="stat">
            <dt>p50</dt>
            <dd>{{ number_format($stats['p50']) }}<small>ms</small></dd>
        </div>
        <div class="stat">
            <dt>p95</dt>
            <dd>{{ number_format($stats['p95']) }}<small>ms</small></dd>
        </div>
        <div class="stat">
            <dt>Failed</dt>
            <dd>{{ number_format($stats['failed']) }}</dd>
        </div>
        <div class="stat">
            <dt>From cache</dt>
            <dd>{{ number_format($stats['cached']) }}</dd>
        </div>
    </dl>

    <nav class="filters" aria-label="Filter requests">
        <a class="chip" href="{{ route('laraclient.logs.index') }}"
           aria-current="{{ $filters['connection'] === '' && ! $filters['failed'] ? 'true' : 'false' }}">all</a>

        @foreach ($connections as $connection)
            <a class="chip"
               href="{{ route('laraclient.logs.index', ['connection' => $connection]) }}"
               aria-current="{{ $filters['connection'] === $connection ? 'true' : 'false' }}">{{ $connection }}</a>
        @endforeach

        <a class="chip"
           href="{{ route('laraclient.logs.index', array_filter(['connection' => $filters['connection'], 'failed' => 1])) }}"
           aria-current="{{ $filters['failed'] ? 'true' : 'false' }}">failures only</a>

        <form class="search" method="GET" action="{{ route('laraclient.logs.index') }}">
            @if ($filters['connection'])
                <input type="hidden" name="connection" value="{{ $filters['connection'] }}">
            @endif
            <label class="sr-only" for="endpoint-filter" hidden>Endpoint contains</label>
            <input id="endpoint-filter" type="search" name="endpoint"
                   value="{{ $filters['endpoint'] }}" placeholder="endpoint contains…">
            <button type="submit">Filter</button>
        </form>
    </nav>

    @if ($logs->isEmpty())
        <div class="empty">
            <strong>No requests match this view.</strong>
            Make a call through LaraClient, or clear the filters above.
        </div>
    @else
        <table class="log-table">
            <thead>
                <tr>
                    <th scope="col">Time</th>
                    <th scope="col" class="hide-sm">Connection</th>
                    <th scope="col">Method</th>
                    <th scope="col">Endpoint</th>
                    <th scope="col" class="col-num">Status</th>
                    <th scope="col" class="col-num">Time</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td>{{ $log->created_at?->format('H:i:s') }}</td>
                        <td class="hide-sm">{{ $log->connection }}</td>
                        <td class="verb">{{ $log->method }}</td>
                        <td class="endpoint">
                            <a href="{{ route('laraclient.logs.show', $log->id) }}">{{ $log->shortEndpoint() }}</a>
                            <span class="host">{{ $log->host() }}</span>
                            @if ($log->cached)<span class="flag">cached</span>@endif
                            @if ($log->attempt > 1)<span class="flag">try {{ $log->attempt }}</span>@endif
                        </td>
                        <td class="col-num status {{ $log->statusClass() }}">{{ $log->status ?? '—' }}</td>
                        <td class="col-num">{{ $log->duration_ms !== null ? $log->duration_ms.'ms' : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="pagination">{{ $logs->links() }}</div>
    @endif
@endsection
