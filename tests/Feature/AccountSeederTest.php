<?php

namespace Tests\Feature;

use App\Models\Account;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_shared_accounts_from_the_balance_csv(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put(
            'seed-data/mable_account_balances.csv',
            "0000000000000001,19.99\n1234567890123456,0.05\n",
        );

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(2, Account::count());
        $this->assertSame('19.99', Account::where('account_number', '0000000000000001')->firstOrFail()->balance);
        $this->assertSame(1999, DB::table('accounts')->where('account_number', '0000000000000001')->value('balance'));
        $this->assertSame('0.05', Account::where('account_number', '1234567890123456')->firstOrFail()->balance);

        Account::where('account_number', '0000000000000001')->firstOrFail()->update(['balance' => '18.99']);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(2, Account::count());
        $this->assertSame('18.99', Account::where('account_number', '0000000000000001')->firstOrFail()->balance);
    }
}
