<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class Fqdn implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('The :attribute must be a valid hostname.');

            return;
        }

        if (strlen($value) > 253) {
            $fail('The :attribute must be a valid hostname.');

            return;
        }

        $labels = explode('.', $value);

        if (count($labels) < 2) {
            $fail('The :attribute must be a valid hostname.');

            return;
        }

        foreach ($labels as $label) {
            if (! preg_match('/^[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?$/', $label)) {
                $fail('The :attribute must be a valid hostname.');

                return;
            }
        }
    }
}
