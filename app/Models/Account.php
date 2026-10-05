<?php

namespace App\Models;

use App\Casts\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['account_number', 'balance'])]
class Account extends Model
{
    protected function casts(): array
    {
        return ['balance' => Money::class];
    }
}
