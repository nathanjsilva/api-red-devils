<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\AuthController;

Route::get('/', function () {
    return response()->json(['message' => 'API Red Devils - Laravel 11']);
});

Route::prefix('players')->group(function () {
    Route::post('/', [PlayerController::class, 'store']);        
    Route::get('/', [PlayerController::class, 'index']);         
    Route::get('/{id}', [PlayerController::class, 'show']);      
    Route::put('/{id}', [PlayerController::class, 'update']);    
    Route::delete('/{id}', [PlayerController::class, 'destroy']); 
});

Route::post('/login', [AuthController::class, 'login']);

