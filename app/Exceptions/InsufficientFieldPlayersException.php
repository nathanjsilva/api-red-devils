<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsufficientFieldPlayersException extends Exception
{
    public function __construct(string $message = 'Número insuficiente de jogadores de linha.')
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => $this->getMessage(),
        ], 400);
    }
}
