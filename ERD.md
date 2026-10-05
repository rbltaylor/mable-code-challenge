# Database ERD

The diagram below shows the application's core tables and their foreign-key relationships. Account numbers on transactions are stored as values; they are not foreign keys to `accounts`.

```mermaid
erDiagram
    companies ||--o{ transaction_batches : submits
    transaction_batches ||--o{ transactions : contains

    companies {
        bigint id PK
        string code UK
        string name
        char api_key_hash UK
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    accounts {
        bigint id PK
        char account_number UK
        bigint balance "integer cents"
        timestamp created_at
        timestamp updated_at
    }

    transaction_batches {
        bigint id PK
        bigint company_id FK
        string idempotency_key
        string csv_path
        char csv_sha256
        string callback_url "nullable"
        string status
        text failure_reason "nullable"
        smallint callback_http_status "nullable"
        timestamp created_at
        timestamp updated_at
    }

    transactions {
        bigint id PK
        bigint transaction_batch_id FK
        int row_number
        char source_account_number
        char destination_account_number
        bigint amount_cents
        string status
        string reason "nullable"
        timestamp created_at
        timestamp updated_at
    }
```

`transaction_batches` has a unique constraint on `(company_id, idempotency_key)`. `transactions` has a unique constraint on `(transaction_batch_id, row_number)`.
