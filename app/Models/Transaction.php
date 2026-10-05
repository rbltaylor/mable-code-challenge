<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['row_number', 'source_account_number', 'destination_account_number', 'amount_cents', 'status', 'reason'])]
class Transaction extends Model
{
    /** @return BelongsTo<TransactionBatch, $this> */
    public function transactionBatch(): BelongsTo
    {
        return $this->belongsTo(TransactionBatch::class);
    }

    protected function casts(): array
    {
        return ['row_number' => 'integer', 'amount_cents' => 'integer'];
    }
}
