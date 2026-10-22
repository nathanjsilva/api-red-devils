<?php

namespace Database\Seeders;

use App\Models\Championship;
use App\Models\ChampionshipPlayer;
use App\Models\Player;
use App\Support\ChampionshipE2EEnvironment;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ChampionshipE2ESeeder extends Seeder
{
    /**
     * Add only 25 fictional field players and five fictional goalkeepers to an
     * already-created, empty, local E2E championship.
     *
     * @return array{players: int, registrations: int, team_links: int, reused: bool}
     */
    public function run(?int $championshipId = null): array
    {
        app(ChampionshipE2EEnvironment::class)->assertAllowed();

        if (! $championshipId) {
            throw new RuntimeException('Informe o campeonato usando o comando championship:e2e-seed {id}.');
        }

        return DB::transaction(function () use ($championshipId): array {
            $championship = Championship::query()->lockForUpdate()->find($championshipId);

            if (! $championship) {
                throw (new ModelNotFoundException)->setModel(Championship::class, [$championshipId]);
            }

            if (! str_starts_with($championship->name, '[E2E]')) {
                throw new RuntimeException('O campeonato precisa ter o prefixo [E2E] no nome.');
            }

            if ($championship->status !== 'draft' || $championship->matches()->exists()) {
                throw new RuntimeException('O seed exige um campeonato em rascunho sem partidas.');
            }

            $teams = $championship->teams()->orderBy('id')->get();
            if ($teams->count() !== 5) {
                throw new RuntimeException('O campeonato E2E precisa conter exatamente cinco times.');
            }

            $foreignRegistration = $championship->players()
                ->whereHas('player', fn ($query) => $query->where('nickname', 'not like', $this->nicknamePrefix($championshipId).'%'))
                ->exists();

            if ($foreignRegistration) {
                throw new RuntimeException('O campeonato já contém jogadores não criados pelo seed E2E.');
            }

            $expected = [];
            foreach ($teams->values() as $teamIndex => $team) {
                foreach (range(1, 5) as $group) {
                    $expected[] = [
                        'nickname' => $this->nickname($championshipId, 'F', $group, $teamIndex + 1),
                        'type' => 'field',
                        'position' => 'linha',
                        'group' => $group,
                        'level' => sprintf('%d.%d', $group, $teamIndex + 1),
                        'team_id' => null,
                        'team_number' => $teamIndex + 1,
                    ];
                }

                $expected[] = [
                    'nickname' => $this->nickname($championshipId, 'G', $teamIndex + 1, 1),
                    'type' => 'goalkeeper',
                    'position' => 'goleiro',
                    'group' => $teamIndex + 1,
                    'level' => null,
                    'team_id' => $team->id,
                    'team_number' => $teamIndex + 1,
                ];
            }

            $expectedNicknames = collect($expected)->pluck('nickname');
            $existingPlayers = Player::withTrashed()->whereIn('nickname', $expectedNicknames)->get();
            if ($existingPlayers->contains(fn (Player $player) => $player->trashed())) {
                throw new RuntimeException('Há apelidos E2E já removidos logicamente; limpe a referência antes de preparar novamente.');
            }
            if ($existingPlayers->contains(fn (Player $player) => $player->position !== collect($expected)->firstWhere('nickname', $player->nickname)['position'])) {
                throw new RuntimeException('Um apelido reservado ao E2E já existe com posição incompatível.');
            }

            $existingPlayerIds = $existingPlayers->modelKeys();
            if ($existingPlayerIds) {
                $externalUse = DB::table('championship_players')
                    ->whereIn('player_id', $existingPlayerIds)
                    ->where('championship_id', '<>', $championshipId)
                    ->exists()
                    || DB::table('match_players')->whereIn('player_id', $existingPlayerIds)->exists()
                    || DB::table('team_players')->whereIn('player_id', $existingPlayerIds)->exists()
                    || DB::table('users')->whereIn('player_id', $existingPlayerIds)->exists();

                if ($externalUse) {
                    throw new RuntimeException('Um jogador com apelido reservado ao E2E já está ligado a dados externos.');
                }
            }

            $playersCreated = 0;
            $registrationsCreated = 0;
            $linksCreated = 0;

            foreach ($expected as $item) {
                $player = Player::query()->where('nickname', $item['nickname'])->first();
                if (! $player) {
                    $player = Player::factory()->create([
                        'name' => fake('pt_BR')->name(),
                        'nickname' => $item['nickname'],
                        'position' => $item['position'],
                    ]);
                    $playersCreated++;
                } elseif ($player->position !== $item['position']) {
                    throw new RuntimeException("O registro {$item['nickname']} existe com posição incompatível.");
                }

                $participant = $championship->players()->where('player_id', $player->id)->first();
                if (! $participant) {
                    $participant = $championship->players()->create([
                        'player_id' => $player->id,
                        'player_type' => $item['type'],
                        'stone_group' => $item['group'],
                        'stone_level' => $item['level'],
                        'sport_position' => $item['type'] === 'field' ? $this->sportPosition($item['group'], $item['team_number']) : null,
                    ]);
                    $registrationsCreated++;
                } elseif ($participant->player_type !== $item['type']
                    || (int) $participant->stone_group !== $item['group']
                    || ($participant->stone_level === null ? null : number_format((float) $participant->stone_level, 1)) !== $item['level']) {
                    throw new RuntimeException("A inscrição E2E {$item['nickname']} diverge dos valores esperados.");
                }

                if ($item['type'] === 'goalkeeper') {
                    $team = $teams->firstWhere('id', $item['team_id']);
                    $assignedTeamIds = $participant->teams()->pluck('championship_teams.id')->map(fn ($id) => (int) $id)->all();
                    if ($assignedTeamIds !== [$team->id]) {
                        if ($assignedTeamIds) {
                            throw new RuntimeException("O goleiro {$item['nickname']} está vinculado a outro time.");
                        }
                        $team->players()->attach($participant->id);
                        $linksCreated++;
                    }
                }
            }

            $fieldParticipants = $championship->players()->where('player_type', 'field')->get();
            foreach ($teams->values() as $teamIndex => $team) {
                $ids = $fieldParticipants
                    ->filter(fn (ChampionshipPlayer $participant) => number_format((float) $participant->stone_level, 1) === sprintf('%d.%d', (int) $participant->stone_group, $teamIndex + 1))
                    ->pluck('id');

                $assigned = $team->players()->where('player_type', 'field')->pluck('championship_players.id');
                if ($assigned->diff($ids)->isNotEmpty()) {
                    throw new RuntimeException("O time {$team->name} já contém vínculos de jogadores que não correspondem ao seed.");
                }
                $missing = $ids->diff($assigned);
                if ($missing->isNotEmpty()) {
                    $team->players()->attach($missing->all());
                    $linksCreated += $missing->count();
                }
            }

            if ($championship->players()->count() !== 30
                || $championship->players()->where('player_type', 'field')->count() !== 25
                || $championship->players()->where('player_type', 'goalkeeper')->count() !== 5
                || $championship->teams()->withCount('players')->get()->sum('players_count') !== 30) {
                throw new RuntimeException('A conferência final do elenco E2E falhou; a transação foi cancelada.');
            }

            return [
                'players' => $playersCreated,
                'registrations' => $registrationsCreated,
                'team_links' => $linksCreated,
                'reused' => $playersCreated === 0 && $registrationsCreated === 0 && $linksCreated === 0,
            ];
        });
    }

    public static function nicknamePrefix(int $championshipId): string
    {
        return "E2E-C{$championshipId}-";
    }

    private function nickname(int $championshipId, string $kind, int $group, int $team): string
    {
        return sprintf('%s%s-G%02d-T%02d', self::nicknamePrefix($championshipId), $kind, $group, $team);
    }

    private function sportPosition(int $group, int $teamNumber): string
    {
        return ['zagueiro', 'meio-campo', 'atacante', 'lateral'][(($group + $teamNumber) % 4)];
    }
}
