<?php

use Illuminate\Support\Facades\Route;
use Usamamuneerchaudhary\LaraClient\Http\Controllers\LogsController;

Route::get('/logs', [LogsController::class, 'index'])->name('logs.index');
