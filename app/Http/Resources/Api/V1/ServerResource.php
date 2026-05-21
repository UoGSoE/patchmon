<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Server
 */
class ServerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'location' => $this->location,
            'schedule' => [
                'cron_expression' => $this->cron_expression,
                'interval' => $this->schedule_interval?->value,
                'interval_label' => $this->schedule_interval?->label(),
                'frequency' => $this->schedule_frequency,
            ],
            'grace' => [
                'value' => $this->grace_value,
                'units' => $this->grace_units->value,
                'units_label' => $this->grace_units->label(),
            ],
            'owner' => [
                'team_id' => $this->team_id,
                'user_id' => $this->user_id,
                'created_by_user_id' => $this->created_by_user_id,
            ],
            'notification_email' => $this->notification_email,
            'sender_email' => $this->sender_email,
            'patch_token' => $this->patch_token,
            'last_patched_at' => $this->last_patched_at?->toIso8601String(),
            'alerting_since' => $this->alerting_since?->toIso8601String(),
            'silenced_until' => $this->silenced_until?->toIso8601String(),
            'silence_reason' => $this->silence_reason,
            'is_overdue' => $this->isOverdue(),
            'is_currently_silenced' => $this->isCurrentlySilenced(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
