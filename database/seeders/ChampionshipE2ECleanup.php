<?php

namespace Database\Seeders;

use App\Models\Championship;
use App\Models\Player;
use App\Support\ChampionshipE2EEnvironment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ChampionshipE2ECleanup extends Seeder
{
    /** Remove only a tagged E2E championship and its unreferenced fake players. */
    public function run(?int $championshipId = null): array
    {
        app(ChampionshipE2EEnvironment::class)->assertAllowed();

        if (! $championshipId) {
            throw new RuntimeException('Informe o campeonato usando o comando championship:e2e-cleanup {id} --confirm.');
        }

        return DB::transaction(function () use ($championshipId): array {
            $championship = Championship::query()->lockForUpdate()->findOrFail($championshipId);
            if (! str_starts_with($championship->name, '[E2E]')) {
                throw new RuntimeException('A limpeza só aceita campeonatos com o prefixo [E2E].');
            }

            $prefix = ChampionshipE2ESeeder::nicknamePrefix($championshipId);
            $fakePlayers = Player::withTrashed()->where('nickname', 'like', $prefix.'%')->get();
            $fakePlayerIds = $fakePlayers->modelKeys();

            $realRegistrationExists = $championship->players()
                ->whereHas('player', fn ($query) => $query->where('nickname', 'not like', $prefix.'%'))
                ->exists();
            if ($realRegistrationExists) {
                throw new RuntimeException('O campeonato contém jogadores sem marcador E2E; nada foi removido.');
            }

            if ($fakePlayerIds) {
                $outsideRegistration = DB::table('championship_players')
                    ->whereIn('player_id', $fakePlayerIds)
                    ->where('championship_id', '<>', $championshipId)
                    ->exists();
                $legacyMatchUse = DB::table('match_players')->whereIn('player_id', $fakePlayerIds)->exists();
                $legacyTeamUse = DB::table('team_players')->whereIn('player_id', $fakePlayerIds)->exists();
                $userLinkExists = DB::table('users')->whereIn('player_id', $fakePlayerIds)->exists();

                if ($outsideRegistration || $legacyMatchUse || $legacyTeamUse || $userLinkExists) {
                    throw new RuntimeException('Há jogador E2E ligado a dados fora do campeonato. Desvincule-o manualmente antes da limpeza.');
                }
            }

            $matchIds = $championship->matches()->pluck('id');
            $participantIds = $championship->players()->pluck('id');
            $teamIds = $championship->teams()->pluck('id');
            $categoryIds = $championship->awardCategories()->pluck('id');

            if ($matchIds->isNotEmpty()) {
                DB::table('championship_match_events')->whereIn('match_id', $matchIds)->delete();
                DB::table('championship_match_participations')->whereIn('match_id', $matchIds)->delete();
                DB::table('championship_odds')->whereIn('match_id', $matchIds)->delete();
                DB::table('championship_matches')->whereIn('id', $matchIds)->delete();
            }

            if ($categoryIds->isNotEmpty()) {
                DB::table('championship_votes')->whereIn('championship_award_category_id', $categoryIds)->delete();
                DB::table('championship_award_results')->whereIn('championship_award_category_id', $categoryIds)->delete();
                DB::table('championship_award_categories')->whereIn('id', $categoryIds)->delete();
            }

            DB::table('championship_goalkeeper_draws')->where('championship_id', $championshipId)->delete();
            DB::table('championship_tie_breakers')->where('championship_id', $championshipId)->delete();
            DB::table('championship_audit_logs')->where('championship_id', $championshipId)->delete();

            if ($participantIds->isNotEmpty()) {
                DB::table('championship_team_players')->whereIn('championship_player_id', $participantIds)->delete();
                DB::table('championship_players')->whereIn('id', $participantIds)->delete();
            }

            if ($teamIds->isNotEmpty()) {
                DB::table('championship_teams')->whereIn('id', $teamIds)->delete();
            }

            $championship->delete();

            foreach ($fakePlayers as $player) {
                $player->forceDelete();
            }

            return ['championships' => 1, 'players' => $fakePlayers->count()];
        });
    }
}
