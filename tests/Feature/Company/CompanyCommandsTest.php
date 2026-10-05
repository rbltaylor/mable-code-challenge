<?php

namespace Tests\Feature\Company;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanyCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'company-command-test-key');
    }

    public function test_create_prompts_for_and_stores_only_a_digest_of_the_api_key(): void
    {
        $this->artisan('company:create', ['name' => 'Alpha Sales'])
            ->expectsQuestion('API key', 'private-api-key')
            ->assertSuccessful();

        $company = Company::firstOrFail();

        $this->assertTrue(Str::isUuid($company->code));
        $this->assertSame('Alpha Sales', $company->name);
        $this->assertSame(Company::digestApiKey('private-api-key'), $company->api_key_hash);
        $this->assertArrayNotHasKey('api_key_hash', $company->toArray());
    }

    public function test_create_uses_a_unique_code_for_duplicate_names(): void
    {
        $existing = Company::factory()->create(['name' => 'Alpha Sales']);

        $this->artisan('company:create', ['name' => 'Alpha Sales'])
            ->expectsQuestion('API key', 'another-private-key')
            ->assertSuccessful();

        $newCompany = Company::where('name', 'Alpha Sales')->whereKeyNot($existing->id)->firstOrFail();

        $this->assertTrue(Str::isUuid($newCompany->code));
        $this->assertNotSame($existing->code, $newCompany->code);
    }

    public function test_update_key_replaces_the_existing_digest(): void
    {
        $company = Company::factory()->withCode('alpha')->withApiKey('old-key')->create();

        $this->artisan('company:update-key', ['code' => 'alpha'])
            ->expectsQuestion('Replacement API key', 'new-key')
            ->assertSuccessful();

        $this->assertSame(Company::digestApiKey('new-key'), $company->fresh()->api_key_hash);
    }

    public function test_delete_soft_deletes_the_company(): void
    {
        $company = Company::factory()->withCode('alpha')->create();

        $this->artisan('company:delete', ['code' => 'alpha'])
            ->expectsConfirmation('Soft-delete company alpha?', 'yes')
            ->assertSuccessful();

        $this->assertSoftDeleted('companies', ['id' => $company->id]);
        $this->assertNull(Company::find($company->id));
    }
}
