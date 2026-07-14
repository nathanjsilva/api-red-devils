<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    /**
     * Resolve o tamanho de página a partir do parâmetro `per_page` da query string,
     * limitado a um máximo razoável para evitar listagens sem paginação.
     */
    protected function perPage(Request $request, int $default = 15, int $max = 100): int
    {
        $perPage = (int) $request->query('per_page', $default);

        return max(1, min($perPage, $max));
    }
}
