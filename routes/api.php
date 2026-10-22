<?php

use App\Http\Controllers\Admin\MatchPlayerController as AdminMatchPlayerController;
use App\Http\Controllers\Admin\PeladaController as AdminPeladaController;
use App\Http\Controllers\Admin\PlayerController as AdminPlayerController;
use App\Http\Controllers\Admin\TeamController as AdminTeamController;
use App\Http\Controllers\Admin\ChampionshipVoterController as AdminChampionshipVoterController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChampionshipVoterController;
use App\Http\Controllers\PeladaController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\ChampionshipController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::get('/players', [PlayerController::class, 'index']);
Route::get('/players/{id}', [PlayerController::class, 'show']);

Route::get('/championships', [ChampionshipController::class, 'index']);
Route::get('/championships/{championship}', [ChampionshipController::class, 'show']);
Route::get('/championships/{championship}/awards', [ChampionshipController::class, 'awards']);
Route::get('/championships/{championship}/statistics', [ChampionshipController::class, 'statistics']);
Route::get('/championships/{championship}/rankings', [ChampionshipController::class, 'rankings']);
Route::middleware('auth:sanctum')->post('/championship-awards/{category}/votes', [ChampionshipController::class, 'vote']);
Route::get('/championships/{championship}/voting/players', [ChampionshipVoterController::class, 'players']);
Route::post('/championships/{championship}/voting/voters', [ChampionshipVoterController::class, 'register'])->middleware('throttle:10,1');
Route::post('/championships/{championship}/voting/session', [ChampionshipVoterController::class, 'login'])->middleware('throttle:20,1');
Route::get('/championships/{championship}/voting/session', [ChampionshipVoterController::class, 'status']);
Route::delete('/championships/{championship}/voting/session', [ChampionshipVoterController::class, 'logout']);
Route::post('/championship-votes/{category}', [ChampionshipVoterController::class, 'vote'])->middleware('throttle:30,1');

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
    Route::get('players/compare', [StatisticsController::class, 'comparePlayers']);
    Route::get('players/{player}', [StatisticsController::class, 'playerStatistics']);
    Route::get('rankings/wins', [StatisticsController::class, 'winsRanking']);
    Route::get('rankings/goals', [StatisticsController::class, 'goalsRanking']);
    Route::get('rankings/assists', [StatisticsController::class, 'assistsRanking']);
    Route::get('rankings/goal-participation', [StatisticsController::class, 'goalParticipationRanking']);
    Route::get('rankings/goal-participations', [StatisticsController::class, 'goalParticipationsRanking']);
    Route::get('rankings/win-rate', [StatisticsController::class, 'winRateRanking']);
    Route::get('rankings/appearances', [StatisticsController::class, 'appearancesRanking']);
    Route::get('rankings/top-scorer-frequency', [StatisticsController::class, 'topScorerFrequencyRanking']);
    Route::get('rankings/top-assister-frequency', [StatisticsController::class, 'topAssisterFrequencyRanking']);
    Route::get('rankings/goalkeepers', [StatisticsController::class, 'goalkeepersRanking']);
    Route::get('goalkeepers', [StatisticsController::class, 'goalkeepers']);
    Route::get('goalkeepers/{player}', [StatisticsController::class, 'goalkeeperStatistics']);
    Route::get('pelada/{peladaId}', [StatisticsController::class, 'peladaStatistics']);
    Route::get('matches/{match}', [StatisticsController::class, 'matchStatistics']);
    Route::get('dashboard', [StatisticsController::class, 'dashboard']);
    Route::get('evolution', [StatisticsController::class, 'evolution']);
    Route::get('peladas-per-month', [StatisticsController::class, 'peladasPerMonth']);
    Route::get('recent-form', [StatisticsController::class, 'recentForm']);
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

    Route::post('championships', [ChampionshipController::class, 'store']);
    Route::post('championships/{championship}/players', [ChampionshipController::class, 'registerPlayer']);
    Route::post('championships/{championship}/assignments', [ChampionshipController::class, 'assignPlayers']);
    Route::post('championships/{championship}/fixtures', [ChampionshipController::class, 'generateFixtures']);
    Route::post('championships/{championship}/start', [ChampionshipController::class, 'start']);
    Route::put('championship-teams/{team}', [ChampionshipController::class, 'updateTeam']);
    Route::post('championships/{championship}/standings/tie-breaker', [ChampionshipController::class, 'resolveTie']);
    Route::get('championship-users', [ChampionshipController::class, 'users']);
    Route::put('championship-users/{user}/player', [ChampionshipController::class, 'linkUserPlayer']);
    Route::get('championships/{championship}/voters', [AdminChampionshipVoterController::class, 'index']);
    Route::put('championship-voters/{voter}/player', [AdminChampionshipVoterController::class, 'updatePlayer']);
    Route::put('championship-voters/{voter}/pin', [AdminChampionshipVoterController::class, 'resetPin']);
    Route::post('championships/{championship}/playoffs', [ChampionshipController::class, 'generatePlayoffs']);
    Route::post('championship-teams/{team}/crest', [ChampionshipController::class, 'uploadCrest']);
    Route::delete('championship-teams/{team}/crest', [ChampionshipController::class, 'removeCrest']);
    Route::put('championships/{championship}/awards/voting-state', [ChampionshipController::class, 'endVoting']);
    Route::post('championships/{championship}/awards/publish', [ChampionshipController::class, 'publishAwards']);
    Route::put('championship-matches/{match}', [ChampionshipController::class, 'updateMatch']);
    Route::post('championship-matches/{match}/events', [ChampionshipController::class, 'addEvent']);
    Route::put('championship-events/{event}', [ChampionshipController::class, 'updateEvent']);
    Route::delete('championship-events/{event}', [ChampionshipController::class, 'deleteEvent']);
    Route::post('championship-matches/{match}/participation', [ChampionshipController::class, 'setParticipation']);
    Route::post('championship-matches/{match}/odds', [ChampionshipController::class, 'calculateOdds']);
});
