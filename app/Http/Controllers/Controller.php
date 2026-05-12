<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    /**
     * Return a standardised success response.
     *
     * Shape: { "success": true, "message": "...", "data": ... }
     */
    protected function success(string $message, mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    /**
     * Return a standardised error response.
     *
     * Shape: { "success": false, "message": "...", "errors": ... }
     */
    protected function error(string $message, mixed $errors = null, int $status = 422): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /**
     * Return a standardised paginated/list response.
     *
     * Shape: { "success": true, "message": "...", "data": paginator }
     */
    protected function successPaginated(string $message, mixed $paginator): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $paginator,
        ]);
    }
}
