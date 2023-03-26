<?php

namespace Usamamuneerchaudhary\LaraClient\Http\Controllers;

use Usamamuneerchaudhary\LaraClient\Models\LaraClientLog;

class LogsController extends Controller
{
    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Foundation\Application|null
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function index(
    ): \Illuminate\Foundation\Application|\Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\Foundation\Application|null
    {
        if (request()->get('endpoint')) {
            $logs = LaraClientLog::where('endpoint', request()->get('endpoint'))->orderBy('created_at',
                'desc')->paginate(10);
        } else {
            $logs = LaraClientLog::orderBy('created_at', 'desc')->paginate(10);
        }
        return view('laraclient::logs.index', compact('logs'));
    }
}
