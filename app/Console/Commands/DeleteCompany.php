<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;

class DeleteCompany extends Command
{
    protected $signature = 'company:delete {code : Company code}';

    protected $description = 'Soft-delete a company';

    public function handle(): int
    {
        $company = Company::where('code', $this->argument('code'))->first();

        if ($company === null) {
            $this->error('Company not found.');

            return self::FAILURE;
        }

        if (! $this->confirm("Soft-delete company {$company->code}?")) {
            $this->comment('Cancelled.');

            return self::SUCCESS;
        }

        $company->delete();

        $this->info("Soft-deleted company {$company->code}.");

        return self::SUCCESS;
    }
}
