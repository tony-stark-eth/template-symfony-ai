# CLAUDE.md

## Project

template-symfony-ai -- Opinionated Symfony 8 + FrankenPHP + AI project template. Includes Docker infrastructure, strict quality tooling, AI integration via OpenRouter, and Claude Code guidelines.

## Quick Start

```bash
make start       # Build and start containers
make quality     # Run all quality checks
make test        # Run all tests
make hooks       # Install git hooks
```

## All Make Targets

### Docker
| Target | Description |
|--------|-------------|
| `make build` | Build Docker images (no cache) |
| `make up` | Start containers (detached, wait for healthy) |
| `make down` | Stop and remove containers |
| `make start` | Build + start |
| `make restart` | Down + up |
| `make logs` | Follow all container logs |
| `make sh` | Shell into PHP container |
| `make worker-logs` | Follow Messenger worker logs |

### Symfony
| Target | Description |
|--------|-------------|
| `make sf c="<cmd>"` | Run any bin/console command |
| `make cc` | Clear Symfony cache |
| `make sf-migrate` | Run Doctrine migrations |

### Code Quality
| Target | Description |
|--------|-------------|
| `make quality` | Run all quality checks (ECS + PHPStan + Rector) |
| `make phpstan` | PHPStan static analysis (level max) |
| `make ecs` | ECS coding standards check |
| `make ecs-fix` | Fix ECS coding standards issues |
| `make rector` | Rector dry-run |
| `make rector-fix` | Apply Rector fixes |

### Testing
| Target | Description |
|--------|-------------|
| `make test` | Run all PHPUnit tests |
| `make test-unit` | Run unit tests only |
| `make test-integration` | Run integration tests only |
| `make infection` | Mutation testing (unit suite, 80/90% MSI) |
| `make coverage` | Generate HTML coverage report |

### TypeScript
| Target | Description |
|--------|-------------|
| `make ts-build` | Compile TypeScript via Bun |
| `make ts-watch` | Watch and compile TypeScript |

### Database
| Target | Description |
|--------|-------------|
| `make db-create` | Create database |
| `make db-drop` | Drop database |
| `make db-reset` | Drop + create + migrate |
| `make export-postgres` | Dump PostgreSQL to `backup/postgres_backup.sql` |
| `make import-postgres` | Restore from backup |

### Git
| Target | Description |
|--------|-------------|
| `make hooks` | Install git hooks from `.githooks/` |

## Domain Overview

```
src/
├── Example/         # Reference domain: Item entity, controller, seed command
├── User/            # Auth (in-memory provider), login/logout controllers
└── Shared/
    ├── AI/          # ModelFailoverPlatform, ModelDiscoveryService, ModelQualityTracker
    ├── Controller/  # Shared controllers
    ├── Command/     # Console commands
    └── Twig/        # Twig extensions
```

## AI Integration

- **Primary model**: `openrouter/free` -- auto-routes to best available free model
- **Fallback chain**: `ModelFailoverPlatform` (PlatformInterface decorator) chains free -> minimax -> glm -> gpt-oss -> qwen -> nemotron
- **Circuit breaker**: `ModelDiscoveryService` -- 3 consecutive failures -> 24h fallback to cached model list
- **Quality tracking**: `ModelQualityTracker` -- cache-backed acceptance/rejection stats per model
- **Model stats**: `app:ai-stats` command shows model quality metrics
- **Smoke test**: `app:ai-smoke-test` command verifies AI connectivity
- **Blocked models**: `OPENROUTER_BLOCKED_MODELS` env var (comma-separated) for persistent manual overrides

## Key Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_SECRET` | Symfony secret key | `change-me-to-a-random-string` |
| `DATABASE_URL` | PostgreSQL DSN | (set in compose) |
| `ADMIN_EMAIL` | Admin login email | `demo@localhost` |
| `ADMIN_PASSWORD_HASH` | Bcrypt/argon2 hash | (demo hash) |
| `OPENROUTER_API_KEY` | OpenRouter API key for AI | (optional) |
| `OPENROUTER_BLOCKED_MODELS` | Comma-separated blocked model IDs | (empty) |
| `NOTIFIER_CHATTER_DSN` | Notifier transport DSN | `null://null` |
| `MESSENGER_TRANSPORT_DSN` | Messenger transport DSN | `doctrine://default` |

## Guidelines

- `.claude/coding-php.md` -- PHP coding rules
- `.claude/coding-typescript.md` -- TypeScript conventions
- `.claude/testing.md` -- Testing & code quality
- `.claude/architecture.md` -- Architecture reference

## Hard Rules

- No `DateTime` -- use `DateTimeImmutable` via `ClockInterface` only
- No `var_dump` / `dump` / `dd` / `print_r`
- No `empty()` -- use explicit checks
- No `ignoreErrors` in phpstan.neon
- No YAML for Symfony config -- PHP format only
- No `time()` / `date()` / `strtotime()` -- use `ClockInterface`
- Interface-first: all service boundaries defined by interface
- Conventional Commits: `feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:`
