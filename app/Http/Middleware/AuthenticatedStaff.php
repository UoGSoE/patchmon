<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate;

class AuthenticatedStaff extends Authenticate
{
    /**
     * Authenticate the request as normal, then require the user to be a member
     * of staff. Patchmon is staff-only, so this gate sits in front of every web
     * route in place of the plain `auth` middleware — if a non-staff user ever
     * slips through SSO, they get a 403 rather than the run of the app.
     */
    protected function authenticate($request, array $guards)
    {
        parent::authenticate($request, $guards);

        abort_unless($request->user()->is_staff, 403);
    }
}
