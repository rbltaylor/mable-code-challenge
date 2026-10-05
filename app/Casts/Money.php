<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Money implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $cents = Str::padLeft((string) $value, 3, '0');

        return Str::substr($cents, 0, -2).'.'.Str::substr($cents, -2);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): int
    {
        if (! is_string($value) || ! Str::isMatch('/^(0|[1-9][0-9]{0,15})\.[0-9]{2}\z/', $value)) {
            throw new InvalidArgumentException("{$key} must be a decimal string with exactly two decimal places.");
        }

        return (int) Str::remove('.', $value);
    }
}
