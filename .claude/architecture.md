# Architecture Reference

## Docker Services

| Service | Image | Purpose |
|---------|-------|---------|
| `php` | `app-php` (FrankenPHP) | Application server (Caddy + PHP 8.4) |
| `database` | `postgres:17` | Primary data store |
| `pgbouncer` | `bitnami/pgbouncer` | Connection pooler (app connects via pgbouncer, tests connect directly) |

### Multi-stage Dockerfile

- `frankenphp_dev`: Full dev environment (Xdebug, Bun, Ember, Composer)
- `frankenphp_prod`: Optimized production build (OPcache, no dev tools)

### Compose Files

- `compose.yaml`: Base services (php, database, pgbouncer)
- `compose.override.yaml`: Dev overrides (ports, volumes, Ember)
- `compose.prod.yaml`: Production overrides (restart policies, resource limits)

## Domain-Driven Design Structure

```
src/
|-- {Domain}/           # Each domain is a self-contained namespace
|   |-- Controller/     # Invokable controllers (one action per class)
|   |-- Entity/         # Doctrine ORM entities
|   |-- Repository/     # Doctrine repositories (interface + implementation)
|   |-- Service/        # Business logic (interface + implementation)
|   |-- ValueObject/    # Immutable domain primitives
|   |-- Message/        # Messenger messages (async commands)
|   |-- MessageHandler/ # Message handlers
|   +-- Exception/      # Domain-specific exceptions
|-- Shared/             # Cross-cutting concerns
|   |-- AI/             # AI infrastructure (used by any domain)
|   |-- Controller/     # Shared controllers (health check, dashboard)
|   |-- Command/        # Console commands
|   +-- Twig/           # Twig extensions
|-- User/               # Authentication domain
+-- Kernel.php
```

### Example Domain (`src/Example/`)

The `Example/` namespace demonstrates the DDD pattern with a minimal entity, controller, and seed command. Use it as a reference when adding new domains, then delete it.

## Adding a New Domain

1. **Create the namespace**: `src/YourDomain/`
2. **Add entity**: `src/YourDomain/Entity/YourEntity.php` (Doctrine ORM attributes)
3. **Register in Doctrine config**: Add mapping to `config/packages/doctrine.php`:
   ```php
   'YourDomain' => [
       'type' => 'attribute',
       'is_bundle' => false,
       'dir' => '%kernel.project_dir%/src/YourDomain/Entity',
       'prefix' => 'App\YourDomain\Entity',
       'alias' => 'YourDomain',
   ],
   ```
4. **Create controller**: `src/YourDomain/Controller/ListYourEntityController.php` (invokable)
5. **Add route**: Route is auto-discovered via PHP attributes on the controller
6. **Generate migration**: `make sf c="doctrine:migrations:diff"`
7. **Run migration**: `make sf-migrate`
8. **Add tests**: Unit tests in `tests/Unit/YourDomain/`, functional in `tests/Functional/YourDomain/`
9. **Update architecture tests**: Add dependency rules to `tests/Architecture/LayerDependencyTest.php`

## AI Infrastructure

### ModelFailoverPlatform

Decorates `PlatformInterface` with model-level failover. When the primary model (`openrouter/free`) fails, it automatically tries fallback models in order:

```
openrouter/free -> minimax -> glm -> gpt-oss -> qwen -> nemotron
```

Wired in `config/services.php` as `ai.platform.openrouter.failover`.

### ModelDiscoveryService

Discovers available free models from the OpenRouter API. Includes a circuit breaker: after 3 consecutive API failures, falls back to a cached model list for 24 hours.

### ModelQualityTracker

Cache-backed service that tracks acceptance/rejection rates per model. Used to identify underperforming models and inform blocked-model configuration.

### Commands

- `app:ai-smoke-test`: Test AI connectivity and model availability
- `app:ai-stats`: Display model quality metrics (acceptance rates, response times)

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_SECRET` | Symfony secret key | (change in production) |
| `DATABASE_URL` | PostgreSQL DSN | (set in compose) |
| `ADMIN_EMAIL` | Admin login email | `demo@localhost` |
| `ADMIN_PASSWORD_HASH` | Bcrypt/argon2 hash | (demo hash) |
| `OPENROUTER_API_KEY` | OpenRouter API key for AI | (optional) |
| `OPENROUTER_BLOCKED_MODELS` | Comma-separated blocked model IDs | (empty) |
| `NOTIFIER_CHATTER_DSN` | Notifier transport DSN | `null://null` |
| `MESSENGER_TRANSPORT_DSN` | Messenger transport DSN | `doctrine://default` |

## Makefile Targets

See `CLAUDE.md` for the complete Make targets reference. Key commands:

```bash
make start     # Build + start containers
make quality   # ECS + PHPStan + Rector
make test      # All PHPUnit tests
make infection # Mutation testing
make hooks     # Install git hooks
```
