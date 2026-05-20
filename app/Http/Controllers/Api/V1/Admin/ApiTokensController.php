<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokensController extends Controller
{
    public function index(): JsonResponse
    {
        $tokens = PersonalAccessToken::query()
            ->with('tokenable')
            ->latest()
            ->get()
            ->map(fn (PersonalAccessToken $token) => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities ?? [],
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
                'owner' => $token->tokenable ? [
                    'id' => $token->tokenable->id,
                    'username' => $token->tokenable->username,
                    'full_name' => $token->tokenable->full_name,
                    'email' => $token->tokenable->email,
                ] : null,
            ]);

        return response()->json(['tokens' => $tokens]);
    }
}
