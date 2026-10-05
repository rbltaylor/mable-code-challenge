<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Company> */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => Str::uuid()->toString(),
            'name' => fake()->company(),
            'api_key_hash' => Company::digestApiKey(Str::random(48)),
        ];
    }

    public function withApiKey(string $apiKey): static
    {
        return $this->state(fn (): array => [
            'api_key_hash' => Company::digestApiKey($apiKey),
        ]);
    }

    public function withCode(string $code): static
    {
        return $this->state(fn (): array => ['code' => $code]);
    }
}
