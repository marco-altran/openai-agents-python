# Development Commands and Code Style Guidelines

## Build & Development
- Install dependencies: `make sync` (requires `uv`)
- Format code: `make format` (runs ruff format)
- Lint code: `make lint` (runs ruff check)
- Type check: `make mypy` (runs mypy in strict mode)
- Run tests: `make tests` (runs pytest)
- Run single test: `uv run pytest tests/path_to_test.py::test_name -v`
- Build docs: `make build-docs` 
- Serve docs locally: `make serve-docs`

## Code Style
- Python 3.9+ compatible code
- 100 character line length
- 4-space indentation (2 spaces for YAML)
- Google docstring convention
- Type hints everywhere (mypy with `strict=true`)
- Use absolute imports, organize with `from agents import ...`
- Exceptions should be properly typed and documented
- Test coverage required for new code
- Follow existing patterns in similar files
- Format imports with ruff (`isort` rules, combine-as-imports=true)
- Async-first design with proper awaits and typing

Remember to run linting, typechecking and tests before submitting changes.