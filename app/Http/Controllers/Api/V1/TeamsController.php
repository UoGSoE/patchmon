<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TeamResource;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamsController extends Controller
{
    /**
     * List the teams the authenticated user can see (their own, or all if admin).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $teams = $user->is_admin
            ? Team::orderBy('name')->get()
            : $user->teams()->orderBy('name')->get();

        return response()->json([
            'teams' => TeamResource::collection($teams),
        ]);
    }
}
