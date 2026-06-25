<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ActivityLogResource;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $activity = ActivityLog::query()
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->input('user_id')))
            ->when($request->filled('server_id'), fn ($query) => $query->where('server_id', $request->input('server_id')))
            ->latest()
            ->paginate(min((int) $request->input('per_page', 50), 200))
            ->withQueryString();

        return response()->json([
            'activity' => ActivityLogResource::collection($activity)->response()->getData(true),
        ]);
    }
}
