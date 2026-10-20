<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FrontendController;
use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/auth')->group(function () {
    Route::get('/csrf', [AuthController::class, 'csrf']);
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware('auth')->group(function () {
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::prefix('api/invoices')->middleware('auth')->group(function () {
    Route::get('/', [InvoiceController::class, 'index']);
    Route::post('/', [InvoiceController::class, 'store'])->middleware('throttle:10,1');
});

Route::get('/{path?}', FrontendController::class)
    ->where('path', '(?!api(?:/|$)|up$).*');
