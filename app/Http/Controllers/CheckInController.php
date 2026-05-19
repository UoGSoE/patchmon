<?php

namespace App\Http\Controllers;

use App\Jobs\RecordCheckIn;
use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CheckInController extends Controller
{
    public function __invoke(Request $request, string $token): Response
    {
        $job = Job::where('check_in_token', $token)->firstOrFail();

        RecordCheckIn::dispatch($job->id, $request->ip(), now());

        return response()->noContent(Response::HTTP_OK);
    }
}
