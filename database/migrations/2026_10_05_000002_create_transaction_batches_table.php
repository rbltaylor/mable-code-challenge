<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('idempotency_key', 128);
            $table->string('csv_path');
            $table->char('csv_sha256', 64);
            $table->string('callback_url', 2048)->nullable();
            $table->string('status')->default('submitted');
            $table->timestamps();

            $table->unique(['company_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_batches');
    }
};
