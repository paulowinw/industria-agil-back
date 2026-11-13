<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PecaController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);

        Route::apiResource('users', UserController::class)
            ->middleware('can:manage-users');

        Route::apiResource('pecas', PecaController::class);
        Route::get('/pecas/{peca}/files/{field}', [PecaController::class, 'download'])
            ->name('pecas.files.show');
    });
});
