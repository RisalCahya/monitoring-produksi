<?php

use App\Http\Controllers\ProductionDashboardController;
use App\Http\Controllers\ProductionHistoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', ProductionDashboardController::class)->name('dashboard');
Route::get('/production-history', ProductionHistoryController::class)->name('production-history.index');
Route::get('/dashboard/data', [ProductionDashboardController::class, 'data'])
    ->middleware('throttle:60,1')
    ->name('dashboard.data');
