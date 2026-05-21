<?php

namespace App\Http\Resources\Api\V1;

use App\Models\PatchEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PatchEvent
 */
class PatchEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patched_at' => $this->patched_at?->toIso8601String(),
            'source_ip' => $this->source_ip,
        ];
    }
}
