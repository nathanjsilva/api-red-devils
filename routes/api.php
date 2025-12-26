<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PeladaController;
use App\Http\Controllers\MatchPlayerController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\TeamController;

// Rotas públicas
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

// Rota pública para cadastrar jogador
Route::post('/players', [PlayerController::class, 'store']);

// Rota pública para criar primeiro admin (apenas se não existir nenhum admin)
Route::post('/setup-first-admin', [AdminController::class, 'setupFirstAdmin']);

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
    Route::get('/peladas/date/{date}', [PeladaController::class, 'getByDate']);
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
        Route::get('pelada/{peladaId}', [StatisticsController::class, 'peladaStatistics']);
    });
    
    // Rotas para organização de times
    Route::prefix('teams')->group(function () {
        Route::get('pelada/{peladaId}/fields', [TeamController::class, 'getTeamFields']);
        Route::get('pelada/{peladaId}/players-with-statistics', [TeamController::class, 'getPeladaPlayersWithStatistics']);
        Route::get('pelada/{peladaId}/players', [TeamController::class, 'getPeladaPlayers']);
        Route::get('pelada/{peladaId}/organized', [TeamController::class, 'getPeladaTeams']);
        Route::post('pelada/{peladaId}/organize', [TeamController::class, 'organizePlayers']);
    });
    
    // Rotas de administração
    Route::prefix('admin')->middleware('admin')->group(function () {
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
        // Rota alternativa: atualizar/criar estatísticas por player_id e pelada_id
        Route::put('peladas/{peladaId}/players/{playerId}/statistics', [AdminController::class, 'updateMatchPlayerByPlayerAndPelada']);
        Route::delete('match-players/{id}', [AdminController::class, 'deleteMatchPlayer']);
        
        // Organização de times
        Route::post('peladas/{peladaId}/organize-teams', [AdminController::class, 'organizeTeams']);
        
        // Gerenciar permissões de admin
        Route::post('players/{id}/make-admin', [AdminController::class, 'makeAdmin']);
        Route::post('players/{id}/remove-admin', [AdminController::class, 'removeAdmin']);
    });
});
