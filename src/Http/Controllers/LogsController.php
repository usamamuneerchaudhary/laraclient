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
    public function index()
    {
        if (request()->get('endpoint')) {
            $logs = LaraClientLog::where('endpoint', request()->get('endpoint'))->orderBy('created_at', 'desc')->get();
        } else {
            $logs = LaraClientLog::orderBy('created_at', 'desc')->get();
        }
        return view('laraclient::logs.index', compact('logs'));
    }
}
