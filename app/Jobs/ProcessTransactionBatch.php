<?php

namespace App\Jobs;

use App\Models\TransactionBatch;
use App\Services\TransactionBatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class ProcessTransactionBatch implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $transactionBatchId) {}

    public function handle(TransactionBatchService $service): void
    {
        $request = TransactionBatch::findOrFail($this->transactionBatchId);

        if (in_array($request->status, ['failed', 'completed'], true)) {
            return;
        }

        $request->update(['status' => 'processing']);
        $result = $service->process($request);

        if (! $result->success) {
            $request->update([
                'status' => 'failed',
                'failure_reason' => $result->error,
            ]);

            return;
        }

        $request->update(['status' => 'completed']);
        $this->sendCallback($request, $service);
    }

    private function sendCallback(TransactionBatch $request, TransactionBatchService $service): void
    {
        if ($request->callback_url === null) {
            return;
        }

        try {
            $response = Http::timeout(10)
                ->withoutRedirecting()
                ->retry(2, 2000, throw: false)
                ->post($request->callback_url, $service->response($request)->data);

            $request->update(['callback_http_status' => $response->status()]);
        } catch (ConnectionException) {
            // No HTTP response exists to record; status lookup remains available.
        }
    }

    public function failed(?Throwable $exception): void
    {
        TransactionBatch::whereKey($this->transactionBatchId)
            ->whereNotIn('status', ['completed', 'failed'])
            ->update([
                'status' => 'failed',
                'failure_reason' => $exception?->getMessage() ?? 'Transfer processing failed.',
            ]);
    }
}
