# Mable Code Challenge

This app provides an API to accept CSV batches of account-to-account transfers for authenticated companies.

Main features:

- Each submission is stored with an idempotency key and is queued for processing.
- The queued job updates account balances, records an outcome for each transfer, and calls a supplied callback URL with the results.
- A company can check progress and results with the same idempotency key.
- Company API keys scope submissions and status lookups to that company.

Additional features:

- Companies are managed via CLI commands - create, update API key and soft-delete.
- Accounts and opening balances can be seeded in the DB with CSV before processing transfers.

Limitations:

- Account opening balances are only managed via DB seeder.
- Companies can update any account. Future scope could link accounts to companies and restrict transactions to associated accounts only.
- Transactions are globally limited to $50,000.00, but configurable via env vars. Future scope could set different limits per company.
- Jobs are processed sequentially and are limited to 1000 records per CSV. The exact number of companies and transactions per day may alter approach.

[Database Diagram](./ERD.md)

## Local setup

Prerequisites:

- PHP 8.5 and Composer
- Docker
- A `sail` shell alias (setup below)

On a fresh clone, install dependencies and create `.env`:

```bash
composer install
cp .env.example .env
```

Add this alias to your shell profile (for example, `~/.zshrc`) and open a new terminal.

```bash
alias sail='./vendor/bin/sail'
```

The following commands will start the Docker container via Sail and setup the Laravel project:

```bash
sail up -d
sail artisan key:generate
sail artisan migrate
```

This command adds pre-commit and pre-push hooks for the project (requires Sail):

```bash
git config core.hooksPath .githooks
```

This command sets up a new Company. The company command prompts for an API key. Use it in the `X-API-Key` header when calling the API.

```bash
sail artisan company:create "Example Company"
```

In a separate terminal, run one queue listener to process submitted batches.

```bash
sail artisan queue:listen
```

Running Tests

```bash
sail phpunit
```

Note: On deployment, use [transfer-worker.service](./deploy/transfer-worker.service) instead to process batches with `systemd`.

### Configuration Notes

- `TRANSFER_MAX_AMOUNT_DOLLARS` sets the per-transfer limit (default `50000`).
- `TRANSACTION_BATCHES_DISK` selects where uploaded CSVs are stored. Local development uses the `local` disk; deployments can configure S3 credentials and use the `s3` disk.
- `QUEUE_CONNECTION` controls Laravel's queue backend. The local setup uses the database queue and needs a running queue listener.
- Set `APP_PORT` and `APP_URL` in `.env` if Sail should serve on a different local port; restart Sail after changing the port.

### Seed Accounts

To seed starting balances before submitting transfers, add CSV data to `storage/app/private/seed-data/mable_account_balances.csv`. See [mable_account_balances_sample.csv](storage/app/private/seed-data/mable_account_balances_sample.csv) for an example. Then run:

```bash
sail artisan db:seed
```

## App functionality

### Manage companies

Create a company:

```bash
sail artisan company:create "Example Company"
# Enter API key: [hidden prompt]
# Created company 2f4f... (use the full printed code)
```

Rotate a company's API key using its code:

```bash
sail artisan company:update-key 2f4f...
```

Soft-delete a company using its code:

```bash
sail artisan company:delete 2f4f...
```

## Submit and check a transaction batch

Both API endpoints require the company's API key in the `X-API-Key` header. Use the key entered when the company was created (or its latest replacement).

### Create a batch

Send a `multipart/form-data` request to `POST /v1/transaction-batches` with:

- `csv`: the transfer CSV file (up to 10 MiB and 1,000 rows)
- `idempotency_key`: a client-generated key of 16–128 letters, digits, dashes, or underscores
- `callback_url` (optional): an HTTPS URL to receive the completed result

Each CSV row has no header and contains source account ID, destination account ID, and positive amount in dollars with exactly two decimal places:

```csv
0000000000000001,0000000000000002,15.25
0000000000000002,0000000000000001,5.00
```

Example using curl (replace the API key and file path):

```bash
curl -X POST http://127.0.0.1/v1/transaction-batches \
  -H 'X-API-Key: YOUR_COMPANY_API_KEY' \
  -F 'idempotency_key=weekday-transfers-20261006' \
  -F 'csv=@transfers.csv;type=text/csv' \
  -F 'callback_url=https://client.example.com/transfer-results'
```

The API responds with HTTP `202 Accepted` once the batch is recorded and queued:

```json
{
  "idempotency_key": "weekday-transfers-20261006",
  "status": "submitted"
}
```

The key identifies a batch for that company. Retrying with the same key, same CSV contents, and same callback URL returns the existing batch. Reusing it with different CSV contents or callback URL returns HTTP `409 Conflict`. The same key may be used by another company. Invalid request fields are rejected before queueing; CSV row validation happens in the worker, and a malformed row fails the whole batch.

### Check batch status

Request `GET /v1/transaction-batches/{idempotency_key}` with the same company API key:

```bash
curl http://127.0.0.1/v1/transaction-batches/weekday-transfers-20261006 \
  -H 'X-API-Key: YOUR_COMPANY_API_KEY'
```

The status is `submitted`, `processing`, `completed`, or `failed`. An unknown key, or a key belonging to a different company, returns `404`; a missing or invalid API key returns `401`. Completed and failed responses include totals, per-row outcomes, and `callback_http_status` (`null` when no callback response was received). For example:

```json
{
  "idempotency_key": "weekday-transfers-20261006",
  "status": "completed",
  "totals": {
    "rows": 2,
    "completed": 1,
    "rejected": 1,
    "amount_transferred": "15.25"
  },
  "outcomes": [
    {
      "row_number": 1,
      "source_account_id": "0000000000000001",
      "destination_account_id": "0000000000000002",
      "amount": "15.25",
      "status": "completed",
      "reason": null
    },
    {
      "row_number": 2,
      "source_account_id": "0000000000000002",
      "destination_account_id": "0000000000000001",
      "amount": "5.00",
      "status": "rejected",
      "reason": "insufficient_funds"
    }
  ],
  "callback_http_status": 200
}
```

## How batch processing works

- A queue worker processes one batch at a time, with rows handled in CSV order.
- The worker validates the whole CSV before applying transfers. Invalid input fails the batch without processing any rows.
- For valid input, successful transfers update both balances. Rejected rows (for example, insufficient funds or an unknown account) are recorded and processing continues; they do not fail the batch.
- A completed batch can include rejected rows. A request-level error fails the batch and may leave earlier row outcomes recorded.
- If provided, the callback URL receives the result by HTTPS POST. Delivery is retried once after two seconds; callback failure does not change the batch status. The status endpoint reports the last HTTP response code, or `null` if none was received.
