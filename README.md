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

Notes:
- Sail container must be running for git hooks to run correctly
