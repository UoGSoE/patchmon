<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\GraceUnit;
use App\Enums\OsType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServerRequest extends FormRequest
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
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'os_type' => ['sometimes', 'required', Rule::enum(OsType::class)],
            'interval_months' => ['sometimes', 'required', 'integer', 'min:1'],
            'grace_value' => ['sometimes', 'required', 'integer', 'min:1'],
            'grace_units' => ['sometimes', 'required', Rule::enum(GraceUnit::class)],
            'team_id' => [
                'sometimes',
                'required',
                Rule::exists('teams', 'id')->where(fn ($q) => $q->whereIn('id', $this->user()->teams()->pluck('teams.id'))),
            ],
            'notification_email' => ['sometimes', 'nullable', 'email'],
            'sender_email' => ['sometimes', 'nullable', 'email'],
        ];
    }
}
