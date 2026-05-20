<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreTeamRequest;
use App\Http\Requests\Api\V1\Admin\UpdateTeamRequest;
use App\Http\Resources\Api\V1\TeamResource;
use App\Models\Team;
use Illuminate\Http\JsonResponse;

class TeamsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'teams' => TeamResource::collection(Team::orderBy('name')->get()),
        ]);
    }

    public function store(StoreTeamRequest $request): JsonResponse
    {
        $team = Team::create($request->validated());

        return (new TeamResource($team))->response()->setStatusCode(201);
    }

    public function show(Team $team): TeamResource
    {
        return new TeamResource($team);
    }

    public function update(UpdateTeamRequest $request, Team $team): TeamResource
    {
        $team->update($request->validated());

        return new TeamResource($team->fresh());
    }

    public function destroy(Team $team): JsonResponse
    {
        $team->delete();

        return response()->json(null, 204);
    }
}
