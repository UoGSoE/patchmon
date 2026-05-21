<?php

namespace App\Http\Controllers;

use App\Jobs\RecordPatchEvent;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PatchEventController extends Controller
{
    public function __invoke(Request $request, string $token): Response
    {
        $server = Server::where('patch_token', $token)->firstOrFail();

        RecordPatchEvent::dispatch($server->id, $request->ip(), now());

        return response()->noContent(Response::HTTP_OK);
    }
}
