<?php

namespace Tests\Feature\TransactionBatch;

use App\Jobs\ProcessTransactionBatch;
use App\Models\Account;
use App\Models\Company;
use App\Models\TransactionBatch;
use App\Services\TransactionBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessTransactionBatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('filesystems.transaction_batches_disk', 'local');
        Storage::fake('local');
    }

    public function test_transfer_updates_balances(): void
    {
        $source = Account::create(['account_number' => '0000000000000001', 'balance' => '100.00']);
        $destination = Account::create(['account_number' => '0000000000000002', 'balance' => '0.00']);
        $request = $this->submission("0000000000000001,0000000000000002,60.00\n");

        $this->runJob($request);

        $this->assertSame('40.00', $source->fresh()->balance); // 100 - 60
        $this->assertSame('60.00', $destination->fresh()->balance); // 0 + 60
        $this->assertSame('completed', $request->transactions()->firstOrFail()->status);
        $this->assertSame('completed', $request->fresh()->status);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_insufficient_funds_rejects_transfer(): void
    {
        $source = Account::create(['account_number' => '0000000000000001', 'balance' => '40.00']);
        $destination = Account::create(['account_number' => '0000000000000002', 'balance' => '0.00']);
        $request = $this->submission("0000000000000001,0000000000000002,50.00\n");

        $this->runJob($request);

        $transaction = $request->transactions()->firstOrFail();
        $this->assertSame('40.00', $source->fresh()->balance);
        $this->assertSame('0.00', $destination->fresh()->balance);
        $this->assertSame('rejected', $transaction->status);
        $this->assertSame('insufficient_funds', $transaction->reason);
        $this->assertSame('completed', $request->fresh()->status);
    }

    public function test_invalid_row_fails_batch(): void
    {
        $source = Account::create(['account_number' => '0000000000000001', 'balance' => '100.00']);
        Account::create(['account_number' => '0000000000000002', 'balance' => '0.00']);
        $request = $this->submission("0000000000000001,0000000000000002,10.00\n0000000000000001,bad,20.00\n");

        $this->runJob($request);

        $this->assertSame('failed', $request->fresh()->status);
        $this->assertStringContainsString('row 2', $request->fresh()->failure_reason);
        $this->assertSame('100.00', $source->fresh()->balance);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_self_transfer_is_rejected(): void
    {
        $source = Account::create(['account_number' => '0000000000000001', 'balance' => '100.00']);
        $request = $this->submission("0000000000000001,0000000000000001,1.00\n");

        $this->runJob($request);

        $this->assertSame('same_account', $request->transactions()->firstOrFail()->reason);
        $this->assertSame('100.00', $source->fresh()->balance);
        $this->assertSame('completed', $request->fresh()->status);
    }

    public function test_amount_over_limit_is_rejected(): void
    {
        config()->set('transfers.max_amount_dollars', 25);
        $source = Account::create(['account_number' => '0000000000000001', 'balance' => '100.00']);
        Account::create(['account_number' => '0000000000000002', 'balance' => '0.00']);
        $request = $this->submission("0000000000000001,0000000000000002,25.01\n");

        $this->runJob($request);

        $this->assertSame('amount_exceeds_limit', $request->transactions()->firstOrFail()->reason);
        $this->assertSame('100.00', $source->fresh()->balance);
        $this->assertSame('completed', $request->fresh()->status);
    }

    public function test_status_includes_totals(): void
    {
        $company = Company::factory()->withApiKey('first-key')->create();
        Account::create(['account_number' => '0000000000000001', 'balance' => '10.00']);
        Account::create(['account_number' => '0000000000000002', 'balance' => '0.00']);
        $request = $this->submission("0000000000000001,0000000000000002,2.00\n", $company);

        $this->runJob($request);

        $this->get('/v1/transaction-batches/'.$request->idempotency_key, ['X-API-Key' => 'first-key'])
            ->assertOk()
            ->assertJsonPath('totals.amount_transferred', '2.00')
            ->assertJsonPath('outcomes.0.status', 'completed');
    }

    public function test_callback_retries_once(): void
    {
        $request = $this->submission('', callbackUrl: 'https://example.com/callback');
        Http::fakeSequence()->push(['error' => 'unavailable'], 503)->push([], 200);

        $this->runJob($request);

        $this->assertSame('completed', $request->fresh()->status);
        $this->assertSame(200, $request->fresh()->callback_http_status);
        Http::assertSentCount(2);
    }

    public function test_failed_callback_records_last_code(): void
    {
        $request = $this->submission('', callbackUrl: 'https://example.com/callback');
        Http::fakeSequence()->push([], 503)->push([], 502);

        $this->runJob($request);

        $this->assertSame('completed', $request->fresh()->status);
        $this->assertSame(502, $request->fresh()->callback_http_status);
        Http::assertSentCount(2);
    }

    private function runJob(TransactionBatch $request): void
    {
        (new ProcessTransactionBatch($request->id))->handle(app(TransactionBatchService::class));
    }

    private function submission(string $csv, ?Company $company = null, ?string $callbackUrl = null): TransactionBatch
    {
        $company ??= Company::factory()->create();
        $path = 'transaction-batches/'.uniqid().'.csv';
        Storage::disk('local')->put($path, $csv);

        return $company->transactionBatches()->create([
            'idempotency_key' => 'unique-request-key-1',
            'csv_path' => $path,
            'csv_sha256' => hash('sha256', $csv),
            'callback_url' => $callbackUrl,
            'status' => 'submitted',
        ]);
    }
}
