<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * Return the authenticated user. Useful for "is my token alive?" checks.
     */
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
