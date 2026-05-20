<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\GraceUnit;
use App\Enums\ScheduleInterval;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'cron_expression' => ['sometimes', 'nullable', 'string', 'max:255'],
            'schedule_interval' => ['sometimes', 'nullable', Rule::enum(ScheduleInterval::class)],
            'schedule_frequency' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'grace_value' => ['sometimes', 'required', 'integer', 'min:1'],
            'grace_units' => ['sometimes', 'required', Rule::enum(GraceUnit::class)],
            'team_id' => [
                'sometimes',
                'nullable',
                Rule::exists('teams', 'id')->where(fn ($q) => $q->whereIn('id', $this->user()->teams()->pluck('teams.id'))),
            ],
            'notification_email' => ['sometimes', 'nullable', 'email'],
            'sender_email' => ['sometimes', 'nullable', 'email'],
        ];
    }
}
