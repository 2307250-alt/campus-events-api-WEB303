<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EventController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);

        // Any logged-in user can browse events.
        Route::get('/events', [EventController::class, 'index']);
        Route::get('/events/{event}', [EventController::class, 'show']);

        // Only organizers and admins can create, update or cancel events.
        Route::middleware('role:organizer,admin')->group(function () {
            Route::post('/events', [EventController::class, 'store']);
            Route::match(['put', 'patch'], '/events/{event}', [EventController::class, 'update']);
            Route::delete('/events/{event}', [EventController::class, 'destroy']);
        });

        // Only students can register for events.
        Route::middleware('role:student')->group(function () {
            Route::post('/events/{event}/register', [EventController::class, 'register']);
        });
    });
});