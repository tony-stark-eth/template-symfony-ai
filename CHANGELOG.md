# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Claude Code guardrails**: `.claude/settings.json` with a deny list for destructive commands and credential paths, and a `bash-guard.sh` PreToolUse hook that blocks force deletes, pushes to main, `--no-verify` and repo-wide discards, naming the safe alternative
- **Hook regression tests**: `.claude/hooks/bash-guard.test.sh`, run by the new `claude-guard` CI job
- **Instruction budget test**: `InstructionBudgetTest` keeps CLAUDE.md under 12 KB and the session-start load under 16 KB, and checks that hook scripts referenced in settings exist

### Fixed

- **CI broken by base image drift**: `dunglas/frankenphp:1-php8.4` moved to FrankenPHP 1.13, where the dev container exits on start. Pinned the base image to `1.12-php8.4`
- **Architecture tests never ran**: the phpat rule classes were not registered with the `phpat.test` tag in `phpstan.neon`, so PHPStan never checked them. Registered both; verified with a planted violation. The naming rule now excludes Maker commands (`MakeDomainException` is a command, not an exception)

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
