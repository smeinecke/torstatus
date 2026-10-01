# Makefile

.PHONY: all format check validate test test-integration integration-test mutation vulture complexity xenon bandit pyright fix reformat-ruff fix-ruff

# Default target: runs format and check
all: validate test

# Format the code using ruff
format:
	uv run ruff format --check --diff .

reformat-ruff:
	uv run ruff format .

# Check the code using ruff
check:
	uv run ruff check .

fix-ruff:
	uv run ruff check . --fix

fix: reformat-ruff fix-ruff
	@echo "Updated code."

test:
	uv run python -m pytest tests -m "not integration"

test-cov:
	uv run python -m pytest tests -m "not integration" --cov --cov-report=xml --cov-report=term-missing

# Docker-based integration tests (builds the compose testbed; needs a docker daemon)
test-integration:
	uv run python -m pytest tests/test_integration_compose.py -v -m integration --timeout=600 --no-cov

integration-test: test-integration

# Mutation testing (mutmut). Runs the non-integration suite per mutant.
mutation:
	uv run mutmut run && uv run mutmut results

vulture:
	uv run vulture . --exclude .venv,migrations,tests --make-whitelist

complexity:
	uv run radon cc . -a -nc

xenon:
	uv run xenon -b D -m B -a B .

bandit:
	uv run bandit -c pyproject.toml -r .

pyright:
	uv run pyright

# Validate the code (format + check)
validate: format check complexity bandit pyright vulture
	@echo "Validation passed. Your code is ready to push."
