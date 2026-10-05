<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        // This checked-in balance file is for coding challenge only.
        $path = 'seed-data/mable_account_balances.csv';
        $file = Storage::disk('local')->readStream($path);

        if ($file === false) {
            throw new RuntimeException("Unable to open account balances file: {$path}");
        }

        try {
            $line = 0;

            while (($row = fgetcsv($file, 0, ',', '"', '')) !== false) {
                $line++;

                $validator = Validator::make(
                    ['row' => $row],
                    ['row' => ['array', 'size:2'], 'row.0' => ['required', 'regex:/^[0-9]{16}$/'], 'row.1' => ['required', 'regex:/^(0|[1-9][0-9]{0,15})\.[0-9]{2}$/']],
                );

                if ($validator->fails()) {
                    throw new RuntimeException("Invalid account balance at CSV line {$line}: {$validator->errors()->first()}");
                }

                Account::firstOrCreate(
                    ['account_number' => $row[0]],
                    ['balance' => $row[1]],
                );
            }
        } finally {
            fclose($file);
        }
    }
}
