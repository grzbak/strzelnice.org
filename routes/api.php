<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\IntervalController;
use App\Http\Controllers\Api\UserController;

Route::get('/intervals', [IntervalController::class, 'index']);
Route::post('/intervals', [IntervalController::class, 'store']);
Route::get('/reports/monthly', [IntervalController::class, 'monthly']);
Route::get('/reports/yearly', [IntervalController::class, 'yearly']);
