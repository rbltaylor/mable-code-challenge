<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionBatch extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'min:16', 'max:128', 'alpha_dash:ascii'],
            'csv' => ['required', 'file', 'max:10240'],
            'callback_url' => ['nullable', 'string', 'url', 'starts_with:https://', 'max:2048'],
        ];
    }
}
