<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreServerRequest;
use App\Http\Requests\Api\V1\UpdateServerRequest;
use App\Http\Resources\Api\V1\PatchEventResource;
use App\Http\Resources\Api\V1\ServerResource;
use App\Models\Server;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ServersController extends Controller
{
    /**
     * Show a single server. 404 if the caller cannot see it.
     */
    public function show(Request $request, Server $server): ServerResource
    {
        if ($request->user()->cannot('view', $server)) {
            abort(404);
        }

        return new ServerResource($server);
    }

    /**
     * Paginated patch event history for a server, newest first.
     */
    #[QueryParameter('per_page', description: 'Items per page (max 200, default 50).', type: 'integer', example: 50)]
    public function patchEvents(Request $request, Server $server): JsonResponse
    {
        if ($request->user()->cannot('view', $server)) {
            abort(404);
        }

        $patchEvents = $server->patchEvents()
            ->orderByDesc('patched_at')
            ->paginate(min((int) $request->input('per_page', 50), 200))
            ->withQueryString();

        return response()->json([
            'patch_events' => PatchEventResource::collection($patchEvents)->response()->getData(true),
        ]);
    }

    /**
     * Silence a server until a future moment. Body: silenced_until (ISO datetime), silence_reason (optional string). Idempotent.
     */
    public function silence(Request $request, Server $server): ServerResource
    {
        if ($request->user()->cannot('update', $server)) {
            abort(404);
        }

        $data = $request->validate([
            'silenced_until' => ['required', 'date', 'after:now'],
            'silence_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $server->silenceUntil(Carbon::parse($data['silenced_until']), $data['silence_reason'] ?? null);

        return new ServerResource($server->fresh());
    }

    /**
     * Clear a server's silence so alerts can resume.
     */
    public function unsilence(Request $request, Server $server): ServerResource
    {
        if ($request->user()->cannot('update', $server)) {
            abort(404);
        }

        $server->unsilence();

        return new ServerResource($server->fresh());
    }

    /**
     * Delete a server. 404 if the caller cannot see it.
     */
    public function destroy(Request $request, Server $server): JsonResponse
    {
        if ($request->user()->cannot('delete', $server)) {
            abort(404);
        }

        $server->delete();

        return response()->json(null, 204);
    }

    /**
     * Partially update a server. Only the fields you include are changed.
     */
    public function update(UpdateServerRequest $request, Server $server): ServerResource
    {
        if ($request->user()->cannot('update', $server)) {
            abort(404);
        }

        $server->update($request->validated());

        return new ServerResource($server->fresh());
    }

    /**
     * Create a server. Personal by default; pass team_id (a team you belong to) to make it team-owned. One of cron_expression or schedule_interval+schedule_frequency is required.
     */
    public function store(StoreServerRequest $request): JsonResponse
    {
        Gate::authorize('create', Server::class);

        $user = $request->user();
        $isCron = filled($request->input('cron_expression'));
        $isTeamOwned = $request->filled('team_id');

        $server = Server::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'location' => $request->input('location'),
            'cron_expression' => $isCron ? $request->input('cron_expression') : null,
            'schedule_interval' => $isCron ? null : $request->input('schedule_interval'),
            'schedule_frequency' => $isCron ? 1 : ($request->input('schedule_frequency') ?? 1),
            'grace_value' => $request->input('grace_value'),
            'grace_units' => $request->input('grace_units'),
            'team_id' => $isTeamOwned ? $request->input('team_id') : null,
            'user_id' => $isTeamOwned ? null : $user->id,
            'created_by_user_id' => $user->id,
            'notification_email' => $request->input('notification_email'),
            'sender_email' => $request->input('sender_email'),
        ]);

        return (new ServerResource($server))->response()->setStatusCode(201);
    }

    /**
     * List servers visible to the authenticated user.
     */
    #[QueryParameter('scope', description: 'Which slice of servers to return. One of: mine (personal only), teams (team-owned only), all (default).', type: 'string', example: 'mine')]
    #[QueryParameter('filter[name]', description: 'Case-insensitive partial match on the server name.', type: 'string', example: 'backup')]
    #[QueryParameter('filter[location]', description: 'Case-insensitive partial match on the server location.', type: 'string', example: 'rankine')]
    #[QueryParameter('filter[team_id]', description: 'Restrict to a specific team id.', type: 'integer', example: 1)]
    #[QueryParameter('filter[user_id]', description: 'Restrict to a specific personal owner id.', type: 'integer', example: 1)]
    #[QueryParameter('sort', description: 'Sort field. Prefix with - for descending. Allowed: name, created_at, last_patched_at.', type: 'string', example: '-last_patched_at')]
    #[QueryParameter('per_page', description: 'Items per page (max 100, default 25).', type: 'integer', example: 25)]
    public function index(Request $request): JsonResponse
    {
        $scope = $request->input('scope', 'all');
        abort_unless(in_array($scope, ['mine', 'teams', 'all'], true), 400, 'scope must be one of: mine, teams, all.');

        $user = $request->user();

        $base = Server::query();
        if (! $user->is_admin) {
            $base = $this->restrictToVisibleServers($base, $user);
        }

        $base = $this->applyScope($base, $scope, $user);

        $servers = QueryBuilder::for($base)
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::partial('location'),
                AllowedFilter::exact('team_id'),
                AllowedFilter::exact('user_id'),
            )
            ->allowedSorts('name', 'created_at', 'last_patched_at')
            ->defaultSort('name')
            ->paginate(min((int) $request->input('per_page', 25), 100))
            ->withQueryString();

        return response()->json([
            'servers' => ServerResource::collection($servers)->response()->getData(true),
            'scope' => $scope,
        ]);
    }

    private function restrictToVisibleServers($query, $user)
    {
        $teamIds = $user->teams()->pluck('teams.id');

        return $query->where(function ($q) use ($user, $teamIds) {
            $q->where('user_id', $user->id)
                ->orWhereIn('team_id', $teamIds);
        });
    }

    private function applyScope($query, string $scope, $user)
    {
        if ($scope === 'mine') {
            return $query->where('user_id', $user->id)->whereNull('team_id');
        }

        if ($scope === 'teams') {
            $teamIds = $user->teams()->pluck('teams.id');

            return $query->whereIn('team_id', $teamIds);
        }

        return $query;
    }
}
