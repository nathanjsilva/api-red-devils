<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PeladaController;
use App\Http\Controllers\MatchPlayerController;

// Rota pública de login
Route::post('/login', [AuthController::class, 'login']);
//rota para cadastrar jogador
Route::post('/players', [PlayerController::class, 'store']);

// Grupo protegido com autenticação Sanctum
Route::middleware('auth:sanctum')->group(function () {
    
    // Rotas de jogadores
    Route::get('/players', [PlayerController::class, 'index']);
    Route::get('/players/{id}', [PlayerController::class, 'show']);
    Route::put('/players/{id}', [PlayerController::class, 'update']);
    Route::delete('/players/{id}', [PlayerController::class, 'destroy']);

    // Rotas de peladas
    Route::get('/peladas', [PeladaController::class, 'index']);
    Route::get('/peladas/{id}', [PeladaController::class, 'show']);
    Route::post('/peladas', [PeladaController::class, 'store']);
    Route::put('/peladas/{id}', [PeladaController::class, 'update']);
    Route::delete('/peladas/{id}', [PeladaController::class, 'destroy']);

    // Rotas para registrar estatísticas de jogadores nas peladas
    Route::post('/match-players', [MatchPlayerController::class, 'store']);
    Route::put('/match-players/{id}', [MatchPlayerController::class, 'update']);
    Route::delete('/match-players/{id}', [MatchPlayerController::class, 'destroy']);
});
