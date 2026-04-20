# Tipovačka

A web application where groups of people submit guesses on upcoming football
match scores, earn points based on configurable per-tournament rules, and
compete on group leaderboards. Czech UI. Built on Symfony 8 / PHP 8.5 /
PostgreSQL 17 / FrankenPHP.

## Quick start

```bash
docker compose up -d
docker compose exec web composer db:reset     # migrate + load fixtures
```

| Service | URL |
|---------|-----|
| Application | http://localhost:8080 |
| Mailpit (sent emails) | http://localhost:8025 |
| PostgreSQL | localhost:5432 |

## Fixture credentials

All fixture users have password `password`.

| Role | Email | Nickname |
|------|-------|----------|
| Admin | admin@tipovacka.test | admin |
| Verified user | user@tipovacka.test | tipovac |
| Unverified | unverified@tipovacka.test | novy_uzivatel |
| Soft-deleted | deleted@tipovacka.test | smazany |

See [.docs/FIXTURES.md](.docs/FIXTURES.md) for the full reference.

## Development commands

```bash
# Full quality gate (phpstan + unit tests)
docker compose exec web composer quality

# All tests (unit + integration)
docker compose exec web composer test

# Code style
docker compose exec web composer cs:fix
docker compose exec web composer cs:check

# Database lifecycle
docker compose exec web composer db:reset                         # drop, migrate, fixtures
docker compose exec web bin/console doctrine:migrations:diff      # generate a new migration
docker compose exec web bin/console doctrine:schema:validate      # must say "in sync"

# Tailwind (dev)
docker compose exec web bin/console tailwind:build
docker compose exec web bin/console tailwind:build --watch
```

## Architecture

CQRS with three Symfony Messenger buses:

- **command.bus** — write operations. `doctrine_transaction` middleware auto-flushes on
  success and rolls back on exception. `DispatchDomainEventsMiddleware` dispatches
  buffered domain events after the transaction commits.
- **query.bus** — read operations via `App\Query\QueryBus` (type-safe via PHPStan generics).
- **event.bus** — domain events with zero-or-more handlers (`allow_no_handlers: true`).

### Key conventions

- PHP 8.4 property hooks replace trivial `getX()` / `isX()` accessors.
- No `Interface` or `Exception` class-name suffixes — prefer domain-oriented names.
- Single-action `final` controllers with class-level `#[Route]` and `__invoke()`.
- Commands, events, and DTOs are `final readonly`.
- Repositories use composition + QueryBuilder — never `ServiceEntityRepository`,
  `findBy()`, `findOneBy()`, or `flush()`.
- Soft-delete everywhere via `SoftDeletable` + `SoftDeletes` trait; list queries
  filter `deletedAt IS NULL` explicitly.
- Migrations are **always** generated via `doctrine:migrations:diff`; partial
  unique indexes are expressed in mapping via
  `#[ORM\UniqueConstraint(options: ['where' => '…'])]`.
- Authorization via voters only. No inline `isGranted('ROLE_*')` checks.

See [CLAUDE.md](CLAUDE.md) for the full convention reference.

## Tech stack

- **Backend**: Symfony 8, PHP 8.5, FrankenPHP, Doctrine ORM 3, Messenger.
- **Database**: PostgreSQL 17.
- **Frontend**: Twig, Tailwind CSS 4, Stimulus, Symfony UX Live Components, Alpine.
- **Rule engine**: auto-discovered via `_instanceof` tagging of `App\Rule\Rule`
  implementations. Adding a scoring rule = one file in `src/Rule/` — the
  `RuleRegistry` picks it up automatically.
- **Observability**: Sentry integration (prod only).
- **Tests**: PHPUnit 13 with DAMA DoctrineTestBundle for per-test rollback.

## CI / CD

GitHub Actions (`.github/workflows/`):

- **Tests** workflow (`test.yml`): phpunit, phpstan level 8, cs-check, and
  migrations-up-to-date validation against a live Postgres container.
- **Release** workflow (`release.yml`): builds a Docker image to
  `ghcr.io/janmikes/tipovacka` on successful Tests and triggers a deploy via
  SSH. Deploy requires `DEPLOY_USERNAME` and `DEPLOY_PRIVATE_KEY` secrets —
  see [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

The application exposes a simple health check at `/-/health-check/liveness`
that verifies the DB connection — used for blue/green gating during deploys.

## Further reading

- [docs/future-notifications.md](docs/future-notifications.md) — deferred
  listeners roadmap keyed by domain event.
- [.docs/FIXTURES.md](.docs/FIXTURES.md) — reference for test fixtures.
- [CLAUDE.md](CLAUDE.md) — development conventions (lots of specifics).
