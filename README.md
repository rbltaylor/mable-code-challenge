# Mable Code Challenge

## Local Setup

Prerequisites: 

- PHP `8.5` with `composer` package manager
- Docker

Setup:

```bash
# Install Dependencies - mainly Laravel Sail
composer install
# Start Sail Docker Container
./vendor/bin/sail up -d
# Generate app key and run migrations
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
# Connect git pre-commit and pre-push hooks
git config core.hooksPath .githooks
```

### Sample Data / Seeder

To add sample account data:

1. Add csv data to `storage/app/private/seed-data/mable_account_balances.csv`. 
See [mable_account_balances_sample.csv](storage/app/private/seed-data/mable_account_balances_sample.csv) for example.
2. Run `sail artisan db:seed`

Notes:
- Sail container must be running for git hooks to run correctly
