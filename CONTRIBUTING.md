# Contributing

## Development Setup

1. Clone the repository
2. Run `make start` to build and start Docker containers
3. Run `make hooks` to install git hooks

## Code Quality

All code must pass quality checks before merging:

```bash
make quality   # ECS + PHPStan + Rector
make test      # All tests
make infection # Mutation testing (unit suite)
```

### Standards

- **PHPStan**: Level max with strict extensions (zero `ignoreErrors`)
- **ECS**: PSR-12 + strict + cleanCode
- **Rector**: PHP 8.4 + Symfony 8 + Doctrine sets
- **PHPUnit**: Unit + integration + functional suites, Xdebug path coverage
- **Infection**: MSI >= 80%, covered MSI >= 90% (unit suite)

### PHP Conventions

- `declare(strict_types=1)` in every file
- `final readonly class` by default
- Interface-first: all service boundaries defined by interface
- `ClockInterface` for all time access (no `new DateTimeImmutable()`, `time()`, `date()`)
- Max 20 lines/method, max 3 params, max ~150 lines/class
- `find*` returns nullable, `get*` throws on not found
- No `DateTime`, `var_dump`, `dump`, `dd`, `empty()`
- No YAML for Symfony config -- PHP format only
- Invokable controllers (single action per class)
- Value objects over primitives for domain concepts

### TypeScript Conventions

- Strict mode, `noUncheckedIndexedAccess: true`
- No `any` type -- use `unknown` and narrow
- Compiled via Bun, served via AssetMapper
- No frameworks (no React, no Stimulus)

## Commit Messages

We use [Conventional Commits](https://www.conventionalcommits.org/):

```
feat: add user profile page
fix: correct timezone handling in scheduler
refactor: extract validation into service interface
test: add integration tests for item repository
docs: update architecture documentation
chore: bump PHPStan to 2.1.x
```

## Pull Requests

- One feature/fix per PR
- All quality checks must pass (`make quality && make test`)
- Include tests for new functionality
- Update documentation if applicable
- Follow the PR template
