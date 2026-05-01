<?php

namespace App\Rules;

use App\Models\ReservedSubdomain;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Subdomain implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $value)) {
            $fail('The :attribute must be 1-63 chars, lowercase alphanumeric + hyphens, no leading/trailing hyphen.');

            return;
        }

        if (ReservedSubdomain::query()->whereKey($value)->exists()) {
            $fail("The subdomain ':input' is reserved.");
        }
    }
}
