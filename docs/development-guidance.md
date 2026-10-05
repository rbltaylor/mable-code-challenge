# Development guidance

- Map all model relationships, including currently unused ones.
- Favor the smallest complete change. No speculative layers, commands, or abstractions.
- Prefer Laravel validation, helpers, and Eloquent over raw PHP equivalents. Validate canonical input; avoid chained `strtolower` and `trim`.
- Keep agent updates and tool use concise; token efficiency matters.
- Use modular services for domain processes shared by CLI or API; services return a Result DTO with success, optional error, optional data.
- Add named factory methods using `state()` for common configurations; keep tests readable.
- Write focused tests for new behavior. Developers run tests manually; agents do not run tests.
