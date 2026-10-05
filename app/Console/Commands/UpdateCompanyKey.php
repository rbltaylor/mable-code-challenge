<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Validator;

class UpdateCompanyKey extends Command
{
    protected $signature = 'company:update-key {code : Company code}';

    protected $description = 'Prompt for a replacement company API key';

    public function handle(): int
    {
        $company = Company::where('code', $this->argument('code'))->first();

        if ($company === null) {
            $this->error('Company not found.');

            return self::FAILURE;
        }

        $apiKey = $this->secret('Replacement API key', false);
        $validator = Validator::make(['api_key' => $apiKey], ['api_key' => ['required', 'string']]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $digest = Company::digestApiKey($apiKey);

        if (Company::withTrashed()->where('api_key_hash', $digest)->whereKeyNot($company->id)->exists()) {
            $this->error('API key already belongs to a company.');

            return self::FAILURE;
        }

        try {
            $company->update(['api_key_hash' => $digest]);
        } catch (UniqueConstraintViolationException) {
            $this->error('API key already belongs to a company.');

            return self::FAILURE;
        }

        $this->info("Updated API key for {$company->code}.");

        return self::SUCCESS;
    }
}
