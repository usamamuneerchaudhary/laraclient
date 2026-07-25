<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Usamamuneerchaudhary\LaraClient\Models\LaraClientLog;

class LogsController
{
    /**
     * The dashboard shows request and response bodies, so it is gated in
     * addition to whatever middleware the config declares.
     */
    public function index(Request $request): View
    {

        Gate::authorize('viewLaraClient');

        $logs = LaraClientLog::query()
            ->forConnection($request->string('connection')->toString() ?: null)
            ->forEndpoint($request->string('endpoint')->toString() ?: null)
            ->when($request->boolean('failed'), fn ($query) => $query->failed())
            ->when(
                $request->integer('slower_than') > 0,
                fn ($query) => $query->slowerThan($request->integer('slower_than')),
            )
            ->latest('id')
            ->paginate((int) config('lara_client.dashboard.per_page', 50))
            ->withQueryString();

        // The trace strip needs the recent window in chronological order,
        // independent of whatever page you are looking at.
        $trace = LaraClientLog::query()
            ->forConnection($request->string('connection')->toString() ?: null)
            ->latest('id')
            ->limit(120)
            ->get(['id', 'status', 'duration_ms', 'method', 'endpoint', 'created_at'])
            ->reverse()
            ->values();

        return view('laraclient::logs.index', [
            'logs' => $logs,
            'trace' => $trace,
            'connections' => array_keys(config('lara_client.connections', [])),
            'stats' => $this->stats($request),
            'filters' => [
                'connection' => $request->string('connection')->toString(),
                'endpoint' => $request->string('endpoint')->toString(),
                'failed' => $request->boolean('failed'),
            ],
        ]);
    }

    public function show(int $log): View
    {
        Gate::authorize('viewLaraClient');

        return view('laraclient::logs.show', [
            'log' => LaraClientLog::query()->findOrFail($log),
        ]);
    }

    /** @return array<string, int|float|string> */
    protected function stats(Request $request): array
    {
        $base = fn () => LaraClientLog::query()
            ->forConnection($request->string('connection')->toString() ?: null)
            ->where('created_at', '>=', now()->subDay());

        $total = $base()->count();
        $failed = $base()->failed()->count();
        $durations = $base()->whereNotNull('duration_ms')->pluck('duration_ms')->sort()->values();

        return [
            'total' => $total,
            'failed' => $failed,
            'error_rate' => $total > 0 ? round($failed / $total * 100, 1) : 0.0,
            'p50' => $this->percentile($durations->all(), 0.50),
            'p95' => $this->percentile($durations->all(), 0.95),
            'cached' => $base()->where('cached', true)->count(),
        ];
    }

    /**
     * @param  list<int>  $values
     */
    protected function percentile(array $values, float $percentile): int
    {
        if ($values === []) {
            return 0;
        }

        $index = (int) floor($percentile * (count($values) - 1));

        return (int) $values[$index];
    }
}
