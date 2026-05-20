<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreJobRequest;
use App\Http\Requests\Api\V1\UpdateJobRequest;
use App\Http\Resources\Api\V1\CheckInResource;
use App\Http\Resources\Api\V1\JobResource;
use App\Models\Job;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class JobsController extends Controller
{
    public function show(Request $request, Job $job): JobResource
    {
        if ($request->user()->cannot('view', $job)) {
            abort(404);
        }

        return new JobResource($job);
    }

    public function checkIns(Request $request, Job $job): JsonResponse
    {
        if ($request->user()->cannot('view', $job)) {
            abort(404);
        }

        $checkIns = $job->checkIns()
            ->orderByDesc('checked_in_at')
            ->paginate(min((int) $request->input('per_page', 50), 200))
            ->withQueryString();

        return response()->json([
            'check_ins' => CheckInResource::collection($checkIns)->response()->getData(true),
        ]);
    }

    public function silence(Request $request, Job $job): JobResource
    {
        if ($request->user()->cannot('update', $job)) {
            abort(404);
        }

        $data = $request->validate([
            'silenced_until' => ['required', 'date', 'after:now'],
            'silence_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $job->silenceUntil(Carbon::parse($data['silenced_until']), $data['silence_reason'] ?? null);

        return new JobResource($job->fresh());
    }

    public function unsilence(Request $request, Job $job): JobResource
    {
        if ($request->user()->cannot('update', $job)) {
            abort(404);
        }

        $job->unsilence();

        return new JobResource($job->fresh());
    }

    public function destroy(Request $request, Job $job): JsonResponse
    {
        if ($request->user()->cannot('delete', $job)) {
            abort(404);
        }

        $job->delete();

        return response()->json(null, 204);
    }

    public function update(UpdateJobRequest $request, Job $job): JobResource
    {
        if ($request->user()->cannot('update', $job)) {
            abort(404);
        }

        $job->update($request->validated());

        return new JobResource($job->fresh());
    }

    public function store(StoreJobRequest $request): JsonResponse
    {
        Gate::authorize('create', Job::class);

        $user = $request->user();
        $isCron = filled($request->input('cron_expression'));
        $isTeamOwned = $request->filled('team_id');

        $job = Job::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
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

        return (new JobResource($job))->response()->setStatusCode(201);
    }

    public function index(Request $request): JsonResponse
    {
        $scope = $request->input('scope', 'all');
        abort_unless(in_array($scope, ['mine', 'teams', 'all'], true), 400, 'scope must be one of: mine, teams, all.');

        $user = $request->user();

        $base = Job::query();
        if (! $user->is_admin) {
            $base = $this->restrictToVisibleJobs($base, $user);
        }

        $base = $this->applyScope($base, $scope, $user);

        $jobs = QueryBuilder::for($base)
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::exact('team_id'),
                AllowedFilter::exact('user_id'),
            )
            ->allowedSorts('name', 'created_at', 'last_checked_in_at')
            ->defaultSort('name')
            ->paginate(min((int) $request->input('per_page', 25), 100))
            ->withQueryString();

        return response()->json([
            'jobs' => JobResource::collection($jobs)->response()->getData(true),
            'scope' => $scope,
        ]);
    }

    private function restrictToVisibleJobs($query, $user)
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
