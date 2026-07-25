<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Usamamuneerchaudhary\LaraClient\Http\Controllers\LogsController;

Route::get('/logs', [LogsController::class, 'index'])->name('laraclient.logs.index');
Route::get('/logs/{log}', [LogsController::class, 'show'])->name('laraclient.logs.show');
