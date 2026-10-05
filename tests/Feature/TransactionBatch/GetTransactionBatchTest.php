<?php

namespace Tests\Feature\TransactionBatch;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetTransactionBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_lookup_is_scoped_to_the_authenticated_company(): void
    {
        $company = Company::factory()->withApiKey('first-key')->create();
        Company::factory()->withApiKey('second-key')->create();
        $company->transactionBatches()->create([
            'idempotency_key' => 'status-key-0001',
            'csv_path' => 'transaction-batches/example.csv',
            'csv_sha256' => hash('sha256', 'example'),
            'status' => 'processing',
        ]);

        $url = '/v1/transaction-batches/status-key-0001';

        // Missing API key
        $this->get($url)
            ->assertUnauthorized();

        // Wront Company
        $this->get($url, ['X-API-Key' => 'second-key'])
            ->assertNotFound();

        // Correct company
        $this->get($url, ['X-API-Key' => 'first-key'])
            ->assertOk()
            ->assertJsonPath('idempotency_key', 'status-key-0001')
            ->assertJsonPath('status', 'processing');
    }
}
