<?php

use App\Http\Controllers\Api\IntervalController;
use Illuminate\Support\Facades\Route;

Route::get('/intervals', [IntervalController::class, 'index']);
Route::post('/intervals', [IntervalController::class, 'store']);
Route::get('/reports/monthly', [IntervalController::class, 'monthly']);
Route::get('/reports/yearly', [IntervalController::class, 'yearly']);
