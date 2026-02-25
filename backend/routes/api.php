<?php

use App\Http\Controllers\MessageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('messages', MessageController::class)->only(['store', 'index', 'show']);

Route::post('messages/{message}/retry', [MessageController::class, 'retryAi']);
Route::post('messages/{message}/channels/{channel}/retry', [MessageController::class, 'retryChannel']);
