<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('accounts', AccountController::class);

    Route::get(
        '/accounts/{account}/transactions',
        [TransactionController::class, 'index']
    );

    Route::post(
        '/accounts/{account}/transactions',
        [TransactionController::class, 'store']
    );
});