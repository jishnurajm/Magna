<?php

declare(strict_types=1);

namespace Magna\Webhooks\Support;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validation rule wrapper around {@see WebhookUrlGuard} for use in
 * WebhookController's create/update request validation (S1-04).
 */
class NotPrivateUrlRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        try {
            WebhookUrlGuard::ensureSafe($value);
        } catch (WebhookUrlBlockedException $e) {
            $fail($e->getMessage());
        }
    }
}
