# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-04-05

### Added

- **Docker infrastructure**: FrankenPHP + PostgreSQL + PgBouncer, multi-stage Dockerfile (dev/prod), Compose with health checks
- **Symfony 8.0**: Full framework with Doctrine ORM, Security, Twig, Messenger, AssetMapper, Notifier
- **AI integration**: Symfony AI Bundle with OpenRouter, ModelFailoverPlatform (model-level failover chain), ModelDiscoveryService (circuit breaker), ModelQualityTracker (per-model stats)
- **Example domain**: `Example/` namespace with Item entity, controller, seed command -- demonstrates DDD pattern
- **Authentication**: In-memory user provider with env-configured admin credentials, login/logout controllers
- **Frontend**: DaisyUI + Tailwind CSS via CDN, TypeScript compiled via Bun, theme toggle (dark/light), relative time display
- **Quality tooling**: PHPStan (level max, 10+ extensions), ECS (PSR-12 + strict), Rector (PHP 8.4 + Symfony 8), Infection (mutation testing, 80/90% MSI)
- **Architecture tests**: PHPat rules for layer dependencies and naming conventions
- **CI/CD**: GitHub Actions (build once, parallel quality + tests, integration tests, mutation tests), Dependabot, security audit workflow, Docker image publishing
- **Claude Code guidelines**: `.claude/` directory with PHP, TypeScript, testing, and architecture documentation
- **Open-source files**: MIT license, CONTRIBUTING.md, SECURITY.md, issue templates, PR template
- **Git hooks**: Pre-commit (ECS + PHPStan) and commit-msg (Conventional Commits) hooks
