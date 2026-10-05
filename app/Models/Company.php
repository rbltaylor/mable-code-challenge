<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

#[Fillable(['code', 'name', 'api_key_hash'])]
#[Hidden(['api_key_hash'])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, SoftDeletes;

    /** @return HasMany<TransactionBatch, $this> */
    public function transactionBatches(): HasMany
    {
        return $this->hasMany(TransactionBatch::class);
    }

    public static function digestApiKey(string $apiKey): string
    {
        $appKey = config('app.key');

        if (! is_string($appKey) || $appKey === '') {
            throw new RuntimeException('Application key is not configured.');
        }

        return hash_hmac('sha256', $apiKey, $appKey);
    }
}
