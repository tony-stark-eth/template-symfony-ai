# Testing & Code Quality

## Quality Tools

| Tool | Version | Purpose | Blocks CI? |
|------|---------|---------|------------|
| PHPStan | 2.1.x | Static analysis (level max) | Yes |
| ECS | 13.0.x | Coding standards (PSR-12 + strict) | Yes |
| Rector | 2.4.x | Automated refactoring (dry-run in CI) | Yes |
| Infection | 0.32.x | Mutation testing | Yes (MSI thresholds) |
| PHPat | 0.12.x | Architecture tests (via PHPStan) | Yes |
| PHPUnit | 13.1.x | Unit + integration tests | Yes |

## PHPStan Extensions

| Extension | What it checks |
|-----------|---------------|
| phpstan-strict-rules | Strict type comparisons, no mixed |
| shipmonk/phpstan-rules | ~40 extra rules, bans `var_dump`/`DateTime`/`time()` |
| tomasvotruba/cognitive-complexity | Max 8/method, 50/class |
| tomasvotruba/type-coverage | 100% return, param, property types |
| phpstan-deprecation-rules | Flags deprecated API usage |
| voku/phpstan-rules | Additional strictness rules |
| phpat/phpat | Architecture dependency rules |
| phpstan-symfony | Symfony container/service analysis |
| phpstan-doctrine | Doctrine type/mapping analysis |
| phpstan-phpunit | PHPUnit best practices |

## PHPUnit Conventions

- **Suites**: `unit` (fast, no I/O) and `integration` (database, filesystem)
- **Coverage**: Xdebug path coverage (`XDEBUG_MODE=coverage`)
- **Execution**: Random order, fail on risky/warning
- **Attributes**: `#[CoversClass(Foo::class)]` required on every test class
- **Stubs vs mocks**: `createStub()` when call count doesn't matter, `createMock()` when it does
- **No mocking finals**: Use `BypassFinals::enable()` in `setUp()` when needed
- **Test naming**: `test{MethodName}{Scenario}{ExpectedBehavior}` or descriptive

## Infection (Mutation Testing)

- Runs against **unit test suite only** (integration tests too slow)
- Thresholds: MSI >= 80%, covered MSI >= 90%
- Excluded from mutation: Entity, Kernel, Controller, Command

## CI Pipeline Order

1. **Parallel**: ECS check + PHPStan + Rector dry-run (no DB needed)
2. **Sequential**: PHPUnit (needs DB) -> Infection (needs test results)
3. **E2E**: Symfony Panther (full Docker stack)

## Running Quality Checks

```bash
make quality          # ECS + PHPStan + Rector
make test             # All tests
make test-unit        # Unit tests only
make test-integration # Integration tests only
make infection        # Mutation testing
make coverage         # HTML coverage report
```
