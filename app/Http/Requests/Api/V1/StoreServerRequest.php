<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\GraceUnit;
use App\Enums\OsType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServerRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'os_type' => ['required', Rule::enum(OsType::class)],
            'interval_months' => ['required', 'integer', 'min:1'],
            'grace_value' => ['required', 'integer', 'min:1'],
            'grace_units' => ['required', Rule::enum(GraceUnit::class)],
            'team_id' => [
                'required',
                Rule::exists('teams', 'id')->where(fn ($q) => $q->whereIn('id', $this->user()->teams()->pluck('teams.id'))),
            ],
            'notification_email' => ['nullable', 'email'],
            'sender_email' => ['nullable', 'email'],
        ];
    }
}
