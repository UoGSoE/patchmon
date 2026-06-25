<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ActivityLog
 */
class ActivityLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->user_name,
            'server_id' => $this->server_id,
            'server_name' => $this->server_name,
            'description' => $this->description,
            'source_ip' => $this->source_ip,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
