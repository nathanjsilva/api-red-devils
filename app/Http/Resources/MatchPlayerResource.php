<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchPlayerResource extends JsonResource
{
    /**
     * Transforma o recurso em um array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'player_id' => $this->player_id,
            'pelada_id' => $this->pelada_id,
            'player' => new PlayerResource($this->whenLoaded('player')),
            'pelada' => new PeladaResource($this->whenLoaded('pelada')),
            'goals' => $this->goals,
            'assists' => $this->assists,
            'goals_conceded' => $this->goals_conceded,
            'is_winner' => $this->is_winner,
            'result' => $this->result ?? ($this->is_winner ? 'win' : 'loss'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
