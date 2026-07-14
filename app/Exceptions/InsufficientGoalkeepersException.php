<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsufficientGoalkeepersException extends Exception
{
    public function __construct(string $message = 'Número insuficiente de goleiros.')
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
