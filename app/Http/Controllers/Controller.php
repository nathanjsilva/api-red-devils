<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    protected function errorResponse(
        string $message,
        int $statusCode = 500,
        ?string $error = null,
        array $extra = []
    ): JsonResponse {
        return response()->json(array_merge([
            'message' => $message,
            'error' => $error ?? $message,
        ], $extra), $statusCode);
    }
}
