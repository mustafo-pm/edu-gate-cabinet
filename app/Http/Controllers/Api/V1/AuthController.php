<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PspStatus;
use App\Models\ApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends ApiController
{
    /**
     * Exchange an API key (key_id + secret) for a Sanctum access token.
     * POST /api/v1/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key_id' => ['required', 'string'],
            'secret' => ['required', 'string'],
        ]);

        $key = ApiKey::withoutGlobalScopes()
            ->where('key_id', $data['key_id'])
            ->whereNull('revoked_at')
            ->first();

        if (! $key || ! Hash::check($data['secret'], $key->secret_hash)) {
            return $this->error('invalid_credentials', 'Invalid API key or secret.', 401);
        }

        $psp = $key->psp;
        if ($psp->status !== PspStatus::Active) {
            return $this->error('psp_inactive', 'This PSP account is not active.', 403);
        }

        $key->forceFill(['last_used_at' => now()])->saveQuietly();

        // Token abilities carry the environment so live/sandbox stay separated.
        $token = $psp->createToken(
            name: $key->key_id,
            abilities: ['env:'.$key->environment->value],
        )->plainTextToken;

        return $this->ok([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'psp' => ['id' => $psp->id, 'name' => $psp->name, 'code' => $psp->code],
            'environment' => $key->environment->value,
        ]);
    }

    /** POST /api/v1/auth/logout — revoke the current token. */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->ok(['revoked' => true]);
    }
}
