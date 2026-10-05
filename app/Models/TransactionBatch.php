<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['idempotency_key', 'csv_path', 'csv_sha256', 'callback_url', 'status', 'failure_reason', 'callback_http_status'])]
class TransactionBatch extends Model
{
    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    protected function casts(): array
    {
        // Keep the callback HTTP code numeric in status responses across database drivers.
        return ['callback_http_status' => 'integer'];
    }
}
