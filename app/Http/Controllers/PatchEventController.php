<?php

namespace App\Http\Controllers;

use App\Jobs\RecordPatchEvent;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

class PatchEventController extends Controller
{
    public function __invoke(Request $request, string $token): Response
    {
        $server = Server::where('patch_token', $token)->firstOrFail();

        $notes = $request->input('notes');
        $patchedBy = null;

        if ($bearerToken = $request->bearerToken()) {
            $accessToken = PersonalAccessToken::findToken($bearerToken);

            if ($accessToken) {
                $patchedBy = $accessToken->tokenable_id;
            } else {
                $notes = trim('[Recorded with an invalid API token] '.($notes ?? ''));
            }
        }

        RecordPatchEvent::dispatch($server->id, $request->ip(), now(), $patchedBy, $notes);

        return response()->noContent(Response::HTTP_OK);
    }
}
