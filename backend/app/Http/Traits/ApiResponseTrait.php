<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    protected function success($data = null, string $message = 'Berhasil', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $data,
            'message' => $message,
        ], $statusCode);
    }

    protected function error(string $message = 'Terjadi kesalahan', $errors = null, int $statusCode = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'errors'  => $errors,
            'message' => $message,
        ], $statusCode);
    }
}