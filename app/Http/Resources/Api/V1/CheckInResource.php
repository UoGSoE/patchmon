<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CheckIn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CheckIn
 */
class CheckInResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'source_ip' => $this->source_ip,
        ];
    }
}
