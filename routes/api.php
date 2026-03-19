<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::get('/players', [PlayerController::class, 'index']);
Route::get('/players/{id}', [PlayerController::class, 'show']);

Route::prefix('statistics')->group(function () {
    Route::get('player/{playerId}/pelada/{peladaId}', [StatisticsController::class, 'playerInPelada']);
    Route::get('player/{playerId}/total', [StatisticsController::class, 'playerTotalStatistics']);
    Route::get('players/overview', [StatisticsController::class, 'playersOverview']);
    Route::get('rankings/wins', [StatisticsController::class, 'winsRanking']);
    Route::get('rankings/goals', [StatisticsController::class, 'goalsRanking']);
    Route::get('rankings/assists', [StatisticsController::class, 'assistsRanking']);
    Route::get('rankings/goal-participation', [StatisticsController::class, 'goalParticipationRanking']);
    Route::get('rankings/goalkeepers', [StatisticsController::class, 'goalkeepersRanking']);
    Route::get('pelada/{peladaId}', [StatisticsController::class, 'peladaStatistics']);
});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::post('players', [AdminController::class, 'storePlayer']);
    Route::put('players/{id}', [AdminController::class, 'updatePlayer']);
    Route::delete('players/{id}', [AdminController::class, 'deletePlayer']);

    Route::get('peladas', [AdminController::class, 'listPeladas']);
    Route::get('peladas/{id}', [AdminController::class, 'showPelada']);
    Route::get('peladas/date/{date}', [AdminController::class, 'getPeladasByDate']);
    Route::post('peladas', [AdminController::class, 'storePelada']);
    Route::put('peladas/{id}', [AdminController::class, 'updatePelada']);
    Route::delete('peladas/{id}', [AdminController::class, 'deletePelada']);

    Route::get('teams/pelada/{peladaId}/fields', [TeamController::class, 'getTeamFields']);
    Route::get('teams/pelada/{peladaId}/players-with-statistics', [TeamController::class, 'getPeladaPlayersWithStatistics']);
    Route::get('teams/pelada/{peladaId}/players', [TeamController::class, 'getPeladaPlayers']);
    Route::get('teams/pelada/{peladaId}/organized', [TeamController::class, 'getPeladaTeams']);
    Route::post('teams/pelada/{peladaId}/organize', [TeamController::class, 'organizePlayers']);
    Route::post('peladas/{peladaId}/organize-teams', [AdminController::class, 'organizeTeams']);

    Route::post('match-players', [AdminController::class, 'storeMatchPlayer']);
    Route::put('match-players/{id}', [AdminController::class, 'updateMatchPlayer']);
    Route::delete('match-players/{id}', [AdminController::class, 'deleteMatchPlayer']);
    Route::put('peladas/{peladaId}/players/{playerId}/statistics', [AdminController::class, 'updateMatchPlayerByPlayerAndPelada']);
});
