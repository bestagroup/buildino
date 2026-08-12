<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    public static function success(
        mixed $data = null,
        ?string $message = null,
        int $status = 200,
        array $meta = []
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if ($meta !== []) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $status);
    }

    public static function error(
        string $message,
        string $code,
        int $status,
        array $errors = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
            'code' => $code,
        ];

        if ($errors !== []) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }
}
