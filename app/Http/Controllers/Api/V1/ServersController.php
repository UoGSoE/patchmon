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
     * Silence a server between two moments. Body: silenced_from (ISO datetime), silenced_until (ISO datetime), silence_reason (optional string). Idempotent.
     */
    public function silence(Request $request, Server $server): ServerResource
    {
        if ($request->user()->cannot('update', $server)) {
            abort(404);
        }

        $data = $request->validate([
            'silenced_from' => ['required', 'date'],
            'silenced_until' => ['required', 'date', 'after_or_equal:silenced_from'],
            'silence_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $server->silenceBetween(
            Carbon::parse($data['silenced_from']),
            Carbon::parse($data['silenced_until']),
            $data['silence_reason'] ?? null,
            $request->user(),
            $request->ip(),
        );

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

        $server->unsilence($request->user(), $request->ip());

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
     * Create a server. Belongs to a team the caller is a member of.
     */
    public function store(StoreServerRequest $request): JsonResponse
    {
        Gate::authorize('create', Server::class);

        $server = Server::create([
            ...$request->validated(),
            'created_by_user_id' => $request->user()->id,
        ]);

        return (new ServerResource($server))->response()->setStatusCode(201);
    }

    /**
     * List servers visible to the authenticated user.
     */
    #[QueryParameter('filter[name]', description: 'Case-insensitive partial match on the server name.', type: 'string', example: 'backup')]
    #[QueryParameter('filter[location]', description: 'Case-insensitive partial match on the server location.', type: 'string', example: 'rankine')]
    #[QueryParameter('filter[team_id]', description: 'Restrict to a specific team id.', type: 'integer', example: 1)]
    #[QueryParameter('filter[os_type]', description: 'Restrict to one OS type (linux, windows, other).', type: 'string', example: 'linux')]
    #[QueryParameter('sort', description: 'Sort field. Prefix with - for descending. Allowed: name, created_at, last_patched_at.', type: 'string', example: '-last_patched_at')]
    #[QueryParameter('per_page', description: 'Items per page (max 100, default 25).', type: 'integer', example: 25)]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $base = Server::query();
        if (! $user->is_admin) {
            $teamIds = $user->teams()->pluck('teams.id');
            $base->whereIn('team_id', $teamIds);
        }

        $servers = QueryBuilder::for($base)
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::partial('location'),
                AllowedFilter::exact('team_id'),
                AllowedFilter::exact('os_type'),
            )
            ->allowedSorts('name', 'created_at', 'last_patched_at')
            ->defaultSort('name')
            ->paginate(min((int) $request->input('per_page', 25), 100))
            ->withQueryString();

        return response()->json([
            'servers' => ServerResource::collection($servers)->response()->getData(true),
        ]);
    }
}
