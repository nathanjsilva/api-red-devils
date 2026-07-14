<?php

use App\Http\Controllers\Admin\MatchPlayerController as AdminMatchPlayerController;
use App\Http\Controllers\Admin\PeladaController as AdminPeladaController;
use App\Http\Controllers\Admin\PlayerController as AdminPlayerController;
use App\Http\Controllers\Admin\TeamController as AdminTeamController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PeladaController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::get('/players', [PlayerController::class, 'index']);
Route::get('/players/{id}', [PlayerController::class, 'show']);

Route::get('/peladas', [PeladaController::class, 'index']);
Route::get('/peladas/date/{date}', [PeladaController::class, 'byDate']);
Route::get('/peladas/{id}', [PeladaController::class, 'show']);

Route::prefix('teams')->group(function () {
    Route::get('pelada/{peladaId}/fields', [TeamController::class, 'getTeamFields']);
    Route::get('pelada/{peladaId}/players-with-statistics', [TeamController::class, 'getPeladaPlayersWithStatistics']);
    Route::get('pelada/{peladaId}/players', [TeamController::class, 'getPeladaPlayers']);
    Route::get('pelada/{peladaId}/organized', [TeamController::class, 'getPeladaTeams']);
});

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

    Route::post('players', [AdminPlayerController::class, 'store']);
    Route::put('players/{id}', [AdminPlayerController::class, 'update']);
    Route::delete('players/{id}', [AdminPlayerController::class, 'destroy']);

    Route::post('peladas', [AdminPeladaController::class, 'store']);
    Route::put('peladas/{id}', [AdminPeladaController::class, 'update']);
    Route::delete('peladas/{id}', [AdminPeladaController::class, 'destroy']);

    Route::post('teams/pelada/{peladaId}/organize', [AdminTeamController::class, 'organizeManual']);
    Route::post('peladas/{peladaId}/organize-teams', [AdminTeamController::class, 'organizeAutomatic']);

    Route::post('match-players', [AdminMatchPlayerController::class, 'store']);
    Route::put('match-players/{id}', [AdminMatchPlayerController::class, 'update']);
    Route::delete('match-players/{id}', [AdminMatchPlayerController::class, 'destroy']);
    Route::put('peladas/{peladaId}/players/{playerId}/statistics', [AdminMatchPlayerController::class, 'upsertByPlayerAndPelada']);
});
