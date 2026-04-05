# template-symfony-ai

Opinionated Symfony 8 + FrankenPHP + AI project template.

[![CI](https://github.com/tony-stark-eth/template-symfony-ai/actions/workflows/ci.yml/badge.svg)](https://github.com/tony-stark-eth/template-symfony-ai/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4.svg)](https://www.php.net/)

A production-ready starting point for Symfony 8 projects with AI integration, strict quality tooling, Docker infrastructure, and Claude Code guidelines. Clone it, run `make start`, and start building.

## What's Included

- **Docker infrastructure**: FrankenPHP (Caddy + PHP 8.4) + PostgreSQL 17 + PgBouncer, multi-stage Dockerfile (dev/prod), health checks
- **Symfony 8.0**: Doctrine ORM, Security, Twig, Messenger, AssetMapper, Notifier, Clock
- **AI integration**: Symfony AI Bundle + OpenRouter with model-level failover, circuit breaker, and quality tracking
- **Authentication**: In-memory user provider with env-configured admin credentials, login/logout pages
- **Frontend**: DaisyUI + Tailwind CSS (CDN, version-pinned), TypeScript compiled via Bun, dark/light theme toggle
- **Quality tooling**: PHPStan (level max, 10+ extensions), ECS (PSR-12 + strict), Rector (PHP 8.4 + Symfony 8), Infection (mutation testing)
- **Architecture tests**: PHPat rules enforcing layer dependencies and naming conventions
- **CI/CD**: GitHub Actions (parallel quality checks, integration tests, mutation tests), Dependabot, security audit, Docker image publishing
- **Claude Code guidelines**: `.claude/` directory with PHP, TypeScript, testing, and architecture documentation
- **Open-source ready**: MIT license, CONTRIBUTING.md, SECURITY.md, issue templates, PR template, git hooks

## Quick Start

1. Click **"Use this template"** on GitHub (or clone directly)
2. Clone your new repository
3. Start the application:

```bash
make start    # Builds Docker images and starts containers
make hooks    # Installs git hooks (ECS + PHPStan pre-commit, conventional commit-msg)
```

4. Open https://localhost:8443
5. Login with `demo@localhost` / `demo` (default credentials)

## Architecture

```
src/
├── Example/             # Reference domain (delete after understanding the pattern)
│   ├── Command/         # SeedExampleCommand
│   ├── Controller/      # ItemController
│   └── Entity/          # Item
├── User/                # Authentication
│   ├── Controller/      # LoginController, LogoutController
│   └── Entity/          # User
└── Shared/
    └── AI/              # AI infrastructure
        ├── Command/     # AiSmokeTestCommand, AiModelStatsCommand
        ├── Platform/    # ModelFailoverPlatform
        ├── Service/     # ModelDiscoveryService, ModelQualityTracker
        └── ValueObject/ # ModelId, ModelIdCollection, ModelQualityStats
```

Each domain is a self-contained namespace following DDD conventions. See the [architecture documentation](docs/architecture.md) for Mermaid diagrams.

## Adding a New Domain

1. Create the namespace: `src/YourDomain/`
2. Add an entity: `src/YourDomain/Entity/YourEntity.php`
3. Register in `config/packages/doctrine.php`:
   ```php
   'YourDomain' => [
       'type' => 'attribute',
       'is_bundle' => false,
       'dir' => '%kernel.project_dir%/src/YourDomain/Entity',
       'prefix' => 'App\YourDomain\Entity',
       'alias' => 'YourDomain',
   ],
   ```
4. Create an invokable controller: `src/YourDomain/Controller/ListYourEntityController.php`
5. Generate and run a migration: `make sf c="doctrine:migrations:diff" && make sf-migrate`
6. Add tests and update architecture rules

## AI Configuration

### OpenRouter Setup

1. Get an API key from [openrouter.ai](https://openrouter.ai/)
2. Add to `.env.local`:
   ```
   OPENROUTER_API_KEY=sk-or-v1-your-key-here
   ```
3. Test connectivity: `make sf c="app:ai-smoke-test"`

### Model Failover

The `ModelFailoverPlatform` automatically chains through free models when the primary model fails:

```
openrouter/free -> minimax -> glm -> gpt-oss -> qwen -> nemotron
```

Configure fallback models in `config/services.php`.

### Quality Tracking

- `ModelQualityTracker` records acceptance/rejection rates per model (cache-backed)
- `ModelDiscoveryService` discovers available models with a circuit breaker (3 failures -> 24h cached fallback)
- View stats: `make sf c="app:ai-stats"`
- Block underperforming models: `OPENROUTER_BLOCKED_MODELS=model/id-1,model/id-2`

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_SECRET` | Symfony secret key | `change-me-to-a-random-string` |
| `DATABASE_URL` | PostgreSQL DSN | (set in compose) |
| `ADMIN_EMAIL` | Admin login email | `demo@localhost` |
| `ADMIN_PASSWORD_HASH` | Bcrypt/argon2 hash | (demo hash) |
| `OPENROUTER_API_KEY` | OpenRouter API key | (optional -- AI features disabled without it) |
| `OPENROUTER_BLOCKED_MODELS` | Comma-separated blocked model IDs | (empty) |
| `NOTIFIER_CHATTER_DSN` | Notifier transport DSN | `null://null` |
| `MESSENGER_TRANSPORT_DSN` | Messenger transport DSN | `doctrine://default` |

## Development Commands

### Docker

| Command | Description |
|---------|-------------|
| `make build` | Build Docker images (no cache) |
| `make up` | Start containers (detached, wait for healthy) |
| `make down` | Stop and remove containers |
| `make start` | Build + start |
| `make restart` | Down + up |
| `make logs` | Follow container logs |
| `make sh` | Shell into PHP container |

### Quality

| Command | Description |
|---------|-------------|
| `make quality` | Run all checks (ECS + PHPStan + Rector) |
| `make phpstan` | PHPStan static analysis (level max) |
| `make ecs` | ECS coding standards check |
| `make ecs-fix` | Auto-fix coding standards |
| `make rector` | Rector dry-run |
| `make rector-fix` | Apply Rector fixes |

### Testing

| Command | Description |
|---------|-------------|
| `make test` | Run all PHPUnit tests |
| `make test-unit` | Unit tests only |
| `make test-integration` | Integration tests only |
| `make infection` | Mutation testing (unit suite, 80/90% MSI) |
| `make coverage` | Generate HTML coverage report |

### Database

| Command | Description |
|---------|-------------|
| `make sf-migrate` | Run Doctrine migrations |
| `make db-reset` | Drop + create + migrate |
| `make export-postgres` | Dump to `backup/postgres_backup.sql` |
| `make import-postgres` | Restore from backup |

### TypeScript

| Command | Description |
|---------|-------------|
| `make ts-build` | Compile TypeScript via Bun |
| `make ts-watch` | Watch and compile |

## Quality Standards

- **PHPStan**: Level max with 10+ extensions (strict rules, cognitive complexity, type coverage, architecture tests)
- **ECS**: PSR-12 + strict + cleanCode rulesets
- **Rector**: PHP 8.4 + Symfony 8 + Doctrine upgrade sets (dry-run in CI)
- **Infection**: MSI >= 80%, covered MSI >= 90% (unit test suite)
- **Git hooks**: Pre-commit runs ECS + PHPStan; commit-msg enforces Conventional Commits

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for development setup, code standards, and PR guidelines.

## License

[MIT](LICENSE)
