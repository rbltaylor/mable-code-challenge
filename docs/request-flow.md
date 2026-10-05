# Request flow

- Each company has its own API key. Require it in the header for submission and that company's status lookup.
- Create and manage companies through a simple CLI; prompt to provide or update their API key.
- Submit daily transfers through an API request with CSV and required idempotency key.
- Validate every CSV row's field count, account ID format, and amount format before queueing; reject whole submission if any row is invalid.
- Check account existence and available funds during processing; report failures per transfer.
- Retry with the same idempotency key returns the original request UUID.
- Queue a job for the request; job processes all its transactions.
- Return request-received response with request UUID after queueing.
- Process each company's queued requests in arrival order, one at a time.
- GET request status by UUID: submitted, processing, completed, or failed.
- Rejected transfers remain row outcomes; request still completes.
- Failed status reserved for request-level processing failure.
- Completed result: outcome per CSV row, reason for each rejection, aggregate totals.
- Submission may include a callback. On completion, job calls requester with same result as status response.
