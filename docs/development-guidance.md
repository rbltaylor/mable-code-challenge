# Development guidance

- Map all model relationships, including currently unused ones.
- Keep domain processes in modular service classes; callable from CLI or API controllers.
- Services return a Result DTO: success boolean, optional error message, optional data.
- Add named factory methods using `state()` for common configurations; keep tests readable.
- Developers run tests manually. Agents do not run tests.
