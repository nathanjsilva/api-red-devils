<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PeladaController;
use App\Http\Controllers\MatchPlayerController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\AdminController;

// Rotas públicas
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

// Rota pública para cadastrar jogador
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
    
    // Rotas de estatísticas e rankings
    Route::prefix('statistics')->group(function () {
        Route::get('player/{playerId}/pelada/{peladaId}', [StatisticsController::class, 'playerInPelada']);
        Route::get('player/{playerId}/total', [StatisticsController::class, 'playerTotalStatistics']);
        Route::get('rankings/wins', [StatisticsController::class, 'winsRanking']);
        Route::get('rankings/goals', [StatisticsController::class, 'goalsRanking']);
        Route::get('rankings/assists', [StatisticsController::class, 'assistsRanking']);
        Route::get('rankings/goal-participation', [StatisticsController::class, 'goalParticipationRanking']);
        Route::get('rankings/goalkeepers', [StatisticsController::class, 'goalkeepersRanking']);
    });
    
    // Rotas de administração
    Route::prefix('admin')->group(function () {
        // Jogadores
        Route::post('players', [AdminController::class, 'storePlayer']);
        Route::put('players/{id}', [AdminController::class, 'updatePlayer']);
        Route::delete('players/{id}', [AdminController::class, 'deletePlayer']);
        
        // Peladas
        Route::post('peladas', [AdminController::class, 'storePelada']);
        Route::put('peladas/{id}', [AdminController::class, 'updatePelada']);
        Route::delete('peladas/{id}', [AdminController::class, 'deletePelada']);
        
        // Estatísticas
        Route::post('match-players', [AdminController::class, 'storeMatchPlayer']);
        Route::put('match-players/{id}', [AdminController::class, 'updateMatchPlayer']);
        Route::delete('match-players/{id}', [AdminController::class, 'deleteMatchPlayer']);
        
        // Organização de times
        Route::post('peladas/{peladaId}/organize-teams', [AdminController::class, 'organizeTeams']);
    });
});
