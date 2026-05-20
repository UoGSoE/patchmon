<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamMembersController extends Controller
{
    public function store(Request $request, Team $team): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $team->users()->syncWithoutDetaching([$data['user_id']]);

        return response()->json([
            'members' => $team->users()->orderBy('surname')->orderBy('forenames')->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'username' => $u->username,
                    'full_name' => $u->full_name,
                    'email' => $u->email,
                ]),
        ], 201);
    }

    public function destroy(Team $team, User $user): JsonResponse
    {
        $team->users()->detach($user->id);

        return response()->json(null, 204);
    }
}
