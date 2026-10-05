<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateCompany extends Command
{
    protected $signature = 'company:create {name : Display name}';

    protected $description = 'Create a company and prompt for its API key';

    public function handle(): int
    {
        $apiKey = $this->secret('API key', false);
        $name = $this->argument('name');

        $validator = Validator::make(
            ['name' => $name, 'api_key' => $apiKey],
            ['name' => ['required', 'max:255'], 'api_key' => ['required', 'string']],
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $digest = Company::digestApiKey($apiKey);

        if (Company::withTrashed()->where('api_key_hash', $digest)->exists()) {
            $this->error('API key already belongs to a company.');

            return self::FAILURE;
        }

        $code = Str::uuid()->toString();

        try {
            Company::create(['code' => $code, 'name' => $name, 'api_key_hash' => $digest]);
        } catch (UniqueConstraintViolationException) {
            $this->error('Company code or API key already exists.');

            return self::FAILURE;
        }

        $this->info("Created company {$code}.");

        return self::SUCCESS;
    }
}
