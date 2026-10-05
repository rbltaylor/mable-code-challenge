<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_batches', function (Blueprint $table): void {
            $table->text('failure_reason')->nullable();
            $table->unsignedSmallInteger('callback_http_status')->nullable();
        });

        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaction_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->char('source_account_number', 16);
            $table->char('destination_account_number', 16);
            $table->unsignedBigInteger('amount_cents');
            $table->string('status');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['transaction_batch_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');

        Schema::table('transaction_batches', function (Blueprint $table): void {
            $table->dropColumn(['failure_reason', 'callback_http_status']);
        });
    }
};
