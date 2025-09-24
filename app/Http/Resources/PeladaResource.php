<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PeladaResource extends JsonResource
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
            'date' => $this->date ? (is_string($this->date) ? $this->date : $this->date->format('Y-m-d')) : null,
            'location' => $this->location,
            'qtd_times' => $this->qtd_times,
            'qtd_jogadores_por_time' => $this->qtd_jogadores_por_time,
            'qtd_goleiros' => $this->qtd_goleiros,
            'players_count' => $this->whenLoaded('matchPlayers', function () {
                return $this->matchPlayers->count();
            }),
            'players' => MatchPlayerResource::collection($this->whenLoaded('matchPlayers')),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
