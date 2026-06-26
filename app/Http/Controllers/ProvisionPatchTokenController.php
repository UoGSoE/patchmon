<?php

namespace App\Http\Controllers;

use App\Enums\OsType;
use App\Events\ActivityOccurred;
use App\Models\Server;
use App\Rules\Fqdn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class ProvisionPatchTokenController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'fqdn' => ['required', 'string', new Fqdn],
        ]);

        // Count every attempt — successes and conflicts alike — to blunt FQDN enumeration.
        $throttleKey = 'provision:'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            return response()->json([
                'message' => 'Too many provisioning attempts. Try again shortly.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        RateLimiter::hit($throttleKey, 60);

        $name = strtolower($request->input('fqdn'));

        $server = Server::where('name', $name)->first();
        $created = false;

        // Unknown FQDN: the FQDN tells us nothing about which team owns the box,
        // so it self-creates in triage with conservative defaults for a human to refine.
        // The script may pass an os_type hint to save a triage click; anything missing
        // or unrecognised falls back to Other rather than failing the provision.
        if (! $server) {
            $server = Server::create([
                'team_id' => null,
                'created_by_user_id' => null,
                'netbox_id' => null,
                'name' => $name,
                'os_type' => OsType::tryFrom((string) $request->input('os_type')) ?? OsType::Other,
                'interval_months' => config('patchmon.triage_defaults.interval_months'),
                'grace_value' => config('patchmon.triage_defaults.grace_value'),
                'grace_units' => config('patchmon.triage_defaults.grace_units'),
            ]);
            $created = true;

            ActivityOccurred::dispatch(null, $server->id, 'Server auto-created via patch provisioning', $request->ip());
        }

        if ($server->patch_token_provisioned_at) {
            $this->logAttempt($request, $name, 'conflict');

            return response()->json([
                'message' => 'A Patchmon token has already been provisioned for this server. Reset it in the web UI.',
            ], Response::HTTP_CONFLICT);
        }

        $server->patch_token_provisioned_at = now();
        $server->save();

        ActivityOccurred::dispatch(null, $server->id, 'Patch token provisioned', $request->ip());

        $this->logAttempt($request, $name, $created ? 'created' : 'revealed');

        return response()->json(['patch_token' => $server->patch_token]);
    }

    private function logAttempt(Request $request, string $fqdn, string $outcome): void
    {
        Log::info('Patch token provision attempt', [
            'fqdn' => $fqdn,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'outcome' => $outcome,
        ]);
    }
}
