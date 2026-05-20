<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreUserRequest;
use App\Http\Requests\Api\V1\Admin\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Job;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UsersController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'users' => UserResource::collection(User::orderBy('surname')->orderBy('forenames')->get()),
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create([
            ...$request->validated(),
            'is_staff' => true,
            'password' => bcrypt(Str::random(64)),
        ]);

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $user->update($request->validated());

        return new UserResource($user->fresh());
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete yourself.'], 422);
        }

        $personalJobCount = Job::where('user_id', $user->id)->count();
        $transferTo = $request->input('transfer_jobs_to');
        $cascade = $request->boolean('delete_personal_jobs');

        if ($personalJobCount > 0 && ! $transferTo && ! $cascade) {
            return response()->json([
                'message' => 'User owns personal jobs. Specify either transfer_jobs_to (a user id) or delete_personal_jobs: true.',
            ], 422);
        }

        if ($transferTo) {
            $request->validate([
                'transfer_jobs_to' => ['integer', 'exists:users,id', 'different:user'],
            ]);

            Job::where('user_id', $user->id)->update(['user_id' => $transferTo]);
            $this->reassignAuthorshipAndDelete($user, $transferTo);

            return response()->json(null, 204);
        }

        $this->reassignAuthorshipAndDelete($user, $request->user()->id);

        return response()->json(null, 204);
    }

    private function reassignAuthorshipAndDelete(User $user, int $authorshipFallbackUserId): void
    {
        Job::where('created_by_user_id', $user->id)
            ->where(function ($q) use ($user) {
                $q->whereNotNull('team_id')->orWhere('user_id', '!=', $user->id);
            })
            ->update(['created_by_user_id' => $authorshipFallbackUserId]);

        $user->delete();
    }
}
