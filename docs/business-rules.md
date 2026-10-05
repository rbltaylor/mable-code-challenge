# Business rules

- Banking service for transfers between customer accounts.
- Company: display name and generated unique code; soft-delete through CLI.
- Parse the supplied balance CSV during setup to seed starting balances, before daily transfers.
- Accounts and balances are shared across companies.
- Keep transfer requests scoped to their submitting company.
- Account IDs: 16 digits. Preserve leading zeros.
- Incoming CSV files: no header row.
- Balance CSV fields: account ID, opening balance.
- Daily transfer CSV fields: source account ID, destination account ID, amount.
- Balances: decimal dollars; two digits after decimal in supplied files.
- Transfer amount: positive decimal dollars, exactly two digits after decimal.
- Store balances as integer cents; parse dollar amounts without floating point arithmetic.
- Process transfers in CSV row order; each result affects later rows.
- Each transfer debits source and credits destination.
- Reject and report a transfer if debit would leave source balance below $0; continue with remaining transfers.
- Reject and report a transfer referencing an account without a seeded balance; continue with remaining transfers.
