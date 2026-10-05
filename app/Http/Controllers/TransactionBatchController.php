<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionBatch;
use App\Jobs\ProcessTransactionBatch;
use App\Models\Company;
use App\Models\TransactionBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class TransactionBatchController extends Controller
{
    public function create(StoreTransactionBatch $request): JsonResponse
    {
        /** @var Company $company */
        $company = $request->attributes->get('company');
        $validated = $request->validated();
        $idempotencyKey = $validated['idempotency_key'];
        $csvFile = $request->file('csv');
        $callbackUrl = $validated['callback_url'] ?? null;
        $uploadDisk = config('filesystems.transaction_batches_disk');

        // The hash compares CSV contents, not file names or byte counts. A company may reuse a key only
        // for the same CSV and callback; if the file cannot be read, we cannot verify that retry.
        $csvContentsHash = hash_file('sha256', $csvFile->getRealPath());

        if ($csvContentsHash === false) {
            throw new RuntimeException('Unable to hash the CSV file.');
        }

        // Handle an ordinary retry before storing another copy of the CSV.
        $existingSubmission = $company->transactionBatches()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingSubmission !== null) {
            return $this->responseForReusedKey($existingSubmission, $csvContentsHash, $callbackUrl);
        }

        $storedCsvPath = $csvFile->store('transaction-batches', $uploadDisk);

        if ($storedCsvPath === false) {
            throw new RuntimeException('Unable to store the CSV file.');
        }

        try {
            // The unique company/key constraint prevents concurrent uploads from creating two records.
            $submission = $company->transactionBatches()->createOrFirst(
                ['idempotency_key' => $idempotencyKey],
                [
                    'csv_path' => $storedCsvPath,
                    'csv_sha256' => $csvContentsHash,
                    'callback_url' => $callbackUrl,
                    'status' => 'submitted',
                ],
            );
        } catch (Throwable $exception) {
            Storage::disk($uploadDisk)->delete($storedCsvPath);

            throw $exception;
        }

        if (! $submission->wasRecentlyCreated) {
            // A concurrent request created the record after our lookup; discard our copy and apply the same retry rules.
            Storage::disk($uploadDisk)->delete($storedCsvPath);

            return $this->responseForReusedKey($submission, $csvContentsHash, $callbackUrl);
        }

        ProcessTransactionBatch::dispatch($submission->id);

        return $this->acceptedResponse($submission);
    }

    private function responseForReusedKey(TransactionBatch $submission, string $csvContentsHash, ?string $callbackUrl): JsonResponse
    {
        if ($submission->csv_sha256 !== $csvContentsHash || $submission->callback_url !== $callbackUrl) {
            return response()->json(['message' => 'Idempotency key already used for a different submission.'], 409);
        }

        return $this->acceptedResponse($submission);
    }

    private function acceptedResponse(TransactionBatch $submission): JsonResponse
    {
        return response()->json([
            'idempotency_key' => $submission->idempotency_key,
            'status' => $submission->status,
        ], 202);
    }
}
