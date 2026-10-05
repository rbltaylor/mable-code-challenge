<?php

namespace Tests\Feature\TransactionBatch;

use App\Jobs\ProcessTransactionBatch;
use App\Models\Company;
use App\Models\TransactionBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostTransactionBatchTest extends TestCase
{
    use RefreshDatabase;

    private const CSV = "0000000000000001,0000000000000002,1.25\n";

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('filesystems.transaction_batches_disk', 'local');
        Storage::fake('local');
        Queue::fake();
    }

    public function test_company_api_key_is_required(): void
    {
        $company = Company::factory()->withApiKey('private-key')->create();

        // Missing key
        $this->post('/v1/transaction-batches', $this->payload())
            ->assertUnauthorized();

        // Invalid key
        $this->post('/v1/transaction-batches', $this->payload(), ['X-API-Key' => 'wrong-key'])
            ->assertUnauthorized();

        // Company is soft-deleted
        $company->delete();
        $this->post('/v1/transaction-batches', $this->payload(), ['X-API-Key' => 'private-key'])
            ->assertUnauthorized();

        $this->assertDatabaseCount('transaction_batches', 0);
        Queue::assertNothingPushed();
    }

    public function test_valid_csv_is_stored_privately_and_job_is_dispatched(): void
    {
        $company = Company::factory()->withApiKey('private-key')->create();

        $this->post('/v1/transaction-batches', $this->payload(), ['X-API-Key' => 'private-key'])
            ->assertAccepted()
            ->assertJsonPath('idempotency_key', 'unique-request-key-1')
            ->assertJsonPath('status', 'submitted');

        $submission = TransactionBatch::sole();
        $this->assertTrue($submission->company->is($company));
        $this->assertSame(hash('sha256', self::CSV), $submission->csv_sha256);
        $this->assertSame(self::CSV, Storage::disk('local')->get($submission->csv_path));
        $this->assertSame('https://example.com/callback', $submission->callback_url);
        Queue::assertPushed(ProcessTransactionBatch::class, 1);
    }

    public function test_short_idempotency_key_reports_minimum_length(): void
    {
        Company::factory()->withApiKey('private-key')->create();

        $this->post('/v1/transaction-batches', $this->payload(idempotencyKey: '123456789012345'), ['X-API-Key' => 'private-key'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.idempotency_key.0', 'The idempotency key field must be at least 16 characters.');
    }

    public function test_invalid_idempotency_key_reports_allowed_characters(): void
    {
        Company::factory()->withApiKey('private-key')->create();

        $this->post('/v1/transaction-batches', $this->payload(idempotencyKey: '1234567890123456!'), ['X-API-Key' => 'private-key'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.idempotency_key.0', 'The idempotency key field must only contain letters, numbers, dashes, and underscores.');
    }

    public function test_csv_file_size_is_limited_to_ten_mebibytes(): void
    {
        Company::factory()->withApiKey('private-key')->create();

        $this->post('/v1/transaction-batches', $this->payload(str_repeat('x', 10 * 1024 * 1024 + 1)), ['X-API-Key' => 'private-key', 'Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('csv');

        $this->assertDatabaseCount('transaction_batches', 0);
        Queue::assertNothingPushed();
    }

    public function test_retry_reuses_record_and_changed_submission_conflicts(): void
    {
        Company::factory()->withApiKey('private-key')->create();
        $headers = ['X-API-Key' => 'private-key'];

        // The first use of this key creates the submission.
        $this->post('/v1/transaction-batches', $this->payload(), $headers)
            ->assertAccepted();

        // Sending the same file and callback with the same key returns the original submission.
        $this->post('/v1/transaction-batches', $this->payload(), $headers)
            ->assertAccepted();

        // The key cannot be reused for a different file or callback.
        $this->post('/v1/transaction-batches', $this->payload("0000000000000001,0000000000000002,2.00\n"), $headers)
            ->assertStatus(409);

        $changedCallback = $this->payload();
        $changedCallback['callback_url'] = 'https://example.com/another';

        $this->post('/v1/transaction-batches', $changedCallback, $headers)
            ->assertStatus(409);

        $this->assertDatabaseCount('transaction_batches', 1);
        $this->assertCount(1, Storage::disk('local')->files('transaction-batches'));
        Queue::assertPushed(ProcessTransactionBatch::class, 1);
    }

    public function test_same_key_can_be_used_by_different_companies(): void
    {
        Company::factory()->withApiKey('first-key')->create();
        Company::factory()->withApiKey('second-key')->create();

        $this->post('/v1/transaction-batches', $this->payload(), ['X-API-Key' => 'first-key'])
            ->assertAccepted();

        $this->post('/v1/transaction-batches', $this->payload(), ['X-API-Key' => 'second-key'])
            ->assertAccepted();

        $this->assertDatabaseCount('transaction_batches', 2);
        Queue::assertPushed(ProcessTransactionBatch::class, 2);
    }

    private function payload(string $csv = self::CSV, string $idempotencyKey = 'unique-request-key-1'): array
    {
        return [
            'idempotency_key' => $idempotencyKey,
            'csv' => UploadedFile::fake()->createWithContent('transfers.csv', $csv),
            'callback_url' => 'https://example.com/callback',
        ];
    }
}
