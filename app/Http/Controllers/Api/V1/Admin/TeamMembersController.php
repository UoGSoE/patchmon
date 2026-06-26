<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Events\ActivityOccurred;
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

        $member = User::findOrFail($data['user_id']);
        ActivityOccurred::dispatch($request->user()->id, null, "Added {$member->full_name} to {$team->name}", $request->ip());

        return response()->json([
            'members' => $team->users()->orderBy('surname')->orderBy('forenames')->get()
                ->map(function ($u) {
                    /** @var User $u */
                    return [
                        'id' => $u->id,
                        'username' => $u->username,
                        'full_name' => $u->full_name,
                        'email' => $u->email,
                    ];
                }),
        ], 201);
    }

    public function destroy(Request $request, Team $team, User $user): JsonResponse
    {
        $team->users()->detach($user->id);

        ActivityOccurred::dispatch($request->user()->id, null, "Removed {$user->full_name} from {$team->name}", $request->ip());

        return response()->json(null, 204);
    }
}
