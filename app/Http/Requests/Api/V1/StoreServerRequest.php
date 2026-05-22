<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\GraceUnit;
use App\Enums\OsType;
use App\Rules\Fqdn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name') && is_string($this->input('name'))) {
            $this->merge(['name' => strtolower(trim($this->input('name')))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', new Fqdn, Rule::unique('servers', 'name')],
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
