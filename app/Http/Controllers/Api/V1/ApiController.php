<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

abstract class ApiController extends Controller
{
    protected function ok(array|\JsonSerializable $data, int $status = 200): JsonResponse
    {
        return response()->json(['status' => 'ok', 'data' => $data], $status);
    }

    protected function error(string $code, string $message, int $status = 422, array $extra = []): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'error' => array_merge(['code' => $code, 'message' => $message], $extra),
        ], $status);
    }
}
