<?php

namespace App\Services;

use App\Casts\Money;
use App\Data\Result;
use App\Models\Account;
use App\Models\TransactionBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class TransactionBatchService
{
    public function process(TransactionBatch $request): Result
    {
        $validation = $this->validateCsv($request);

        if (! $validation->success) {
            return $validation;
        }

        $stream = $this->openCsv($request);

        try {
            $rowNumber = 0;

            while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                $rowNumber++;
                $this->processRow($request, $rowNumber, $row);
            }
        } finally {
            fclose($stream);
        }

        return new Result(true);
    }

    public function response(TransactionBatch $request): Result
    {
        $data = [
            'idempotency_key' => $request->idempotency_key,
            'status' => $request->status,
        ];

        if ($request->status === 'failed') {
            $data['failure_reason'] = $request->failure_reason;
        }

        if (in_array($request->status, ['completed', 'failed'], true)) {
            $outcomes = $request->transactions()->orderBy('row_number')->get();
            $successful = $outcomes->where('status', 'completed');

            $data['totals'] = [
                'rows' => $outcomes->count(),
                'completed' => $successful->count(),
                'rejected' => $outcomes->where('status', 'rejected')->count(),
                'amount_transferred' => Money::fromCents((int) $successful->sum('amount_cents')),
            ];
            $data['outcomes'] = $outcomes->map(fn ($outcome): array => [
                'row_number' => $outcome->row_number,
                'source_account_id' => $outcome->source_account_number,
                'destination_account_id' => $outcome->destination_account_number,
                'amount' => Money::fromCents((int) $outcome->amount_cents),
                'status' => $outcome->status,
                'reason' => $outcome->reason,
            ])->all();
        }

        $data['callback_http_status'] = $request->callback_http_status;

        return new Result(true, data: $data);
    }

    private function validateCsv(TransactionBatch $request): Result
    {
        $stream = $this->openCsv($request);

        try {
            $rowNumber = 0;

            while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                $rowNumber++;

                if ($rowNumber > 1000) {
                    return new Result(false, 'CSV exceeds 1,000 rows.');
                }

                $validator = Validator::make(
                    ['row' => $row],
                    [
                        'row' => ['array', 'size:3'],
                        'row.0' => ['required', 'regex:/\A[0-9]{16}\z/'],
                        'row.1' => ['required', 'regex:/\A[0-9]{16}\z/'],
                        'row.2' => ['required', 'regex:/\A(?:0|[1-9][0-9]{0,14})\.[0-9]{2}\z/', 'not_in:0.00'],
                    ],
                );

                if ($validator->fails()) {
                    return new Result(false, "Invalid CSV row {$rowNumber}: {$validator->errors()->first()}");
                }
            }

            return new Result(true);
        } finally {
            fclose($stream);
        }
    }

    private function processRow(TransactionBatch $request, int $rowNumber, array $row): void
    {
        [$sourceNumber, $destinationNumber, $amount] = $row;
        $amountCents = Money::toCents($amount);

        DB::transaction(function () use ($request, $rowNumber, $sourceNumber, $destinationNumber, $amountCents): void {
            if ($request->transactions()->where('row_number', $rowNumber)->exists()) {
                return;
            }

            $reason = null;

            if ($sourceNumber === $destinationNumber) {
                $reason = 'same_account';
            } elseif ($amountCents > (int) config('transfers.max_amount_dollars') * 100) {
                $reason = 'amount_exceeds_limit';
            } else {
                // Assumption: an authenticated company may transfer between any seeded accounts; accounts are shared across companies.
                $accounts = Account::whereIn('account_number', [$sourceNumber, $destinationNumber])
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('account_number');
                $source = $accounts->get($sourceNumber);
                $destination = $accounts->get($destinationNumber);

                if ($source === null || $destination === null) {
                    $reason = 'account_not_found';
                } elseif ((int) $source->getRawOriginal('balance') < $amountCents) {
                    $reason = 'insufficient_funds';
                } else {
                    $source->balance = Money::fromCents((int) $source->getRawOriginal('balance') - $amountCents);
                    $destination->balance = Money::fromCents((int) $destination->getRawOriginal('balance') + $amountCents);
                    $source->save();
                    $destination->save();
                }
            }

            $request->transactions()->create([
                'row_number' => $rowNumber,
                'source_account_number' => $sourceNumber,
                'destination_account_number' => $destinationNumber,
                'amount_cents' => $amountCents,
                'status' => $reason === null ? 'completed' : 'rejected',
                'reason' => $reason,
            ]);
        }, 3);
    }

    private function openCsv(TransactionBatch $request)
    {
        $stream = Storage::disk(config('filesystems.transaction_batches_disk'))->readStream($request->csv_path);

        if ($stream === false) {
            throw new RuntimeException('Unable to open the stored transfer CSV.');
        }

        return $stream;
    }
}
