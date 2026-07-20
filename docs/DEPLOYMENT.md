# Deployment

Production is **Wtips** at <https://wtips.cz>, running on the shared **lily** host
(`lily.srv.thedevs.cz`). The image is `ghcr.io/janmikes/wtips`. The old
`tipovacka.thedevs.cz` instance is **frozen and not auto-deployed** — ignore it.

> The deployment *infrastructure* (compose stack, Traefik, secrets, backups) lives in a
> separate repo checked out on the box at `~/www/lily.srv/apps/wtips/` — this app repo
> only builds the image and pings a deploy webhook. The authoritative compose file is
> `~/www/lily.srv/apps/wtips/compose.yaml`.

## Pipeline (push-to-main → live)

1. Merge to `main`.
2. The **`Tests`** workflow (`.github/workflows/test.yml`) runs phpunit, phpstan,
   php-cs-fixer, `migrations-up-to-date` and `schema:validate`. If it fails, nothing ships.
3. On `Tests` success the **`Release`** workflow (`.github/workflows/release.yml`) calls the
   shared reusable workflow `TheDevs-cz/ci/.github/workflows/_ship.yml@v1`, which:
   - builds & pushes `ghcr.io/janmikes/wtips` (tagged with the commit SHA; `APP_VERSION`
     baked in as a build arg),
   - **HMAC-POSTs** `https://deploy.lily.srv.thedevs.cz/hooks/wtips` (per-app secret
     `DEPLOY_WEBHOOK_SECRET`, set on this repo).
4. The lily deploy webhook pulls the new image and runs a **blue-green** rollout of the
   `web` service: it scales `web` up alongside the running version, waits for the new
   containers' `/-/health-check/liveness` to go healthy, flips Traefik, then stops the old
   version. No SSH from CI anymore.

`await_deploy: false` — the Release job returns after the webhook is accepted; watch the
rollout on the box, not in GitHub Actions.

## The stack on the box (`compose.yaml`)

| Service | Role |
|---|---|
| `web` | FrankenPHP/Symfony, Traefik-routed, **blue-green** (scaled to 2N during rollout), Docker healthcheck on `/-/health-check/liveness`. Runs DB migrations on boot. |
| `messenger-consumer` | Symfony Messenger worker. **No** Traefik, **no** blue-green, **skips** migrations. |
| `db` | This app's own **PostgreSQL 17** (pinned to match source glibc/collation). Named external volume `wtips_pgdata`. Nightly `pg_dump` via label autodiscovery. |
| `adminer` | Public DB admin at `db-admin.wtips.cz`. |
| `db-exporter` | Postgres metrics for Prometheus. |

Routing: `wtips.cz` is canonical; `www.wtips.cz` 301-redirects to the apex. Only Traefik is
public — services talk over the internal Docker network, no published ports.

### Migrations run on the WEB container boot

`.docker/on-startup.sh` runs
`bin/console doctrine:migrations:migrate -vv --allow-no-migration --all-or-nothing
--no-interaction` on `web` startup (the blue-green `start_period` covers it). The
`messenger-consumer` sets `SKIP_DATABASE_MIGRATIONS=true` so a worker restart never races the
schema — migrations happen exactly once per rollout, on the web container.

### The worker consumes both transports

The worker command is:

```
bin/console messenger:consume async scheduler_default -vv --time-limit 3600 --memory-limit 256M
```

`async` carries event-driven side effects (emails, notification delivery, recalculation).
`scheduler_default` is the **symfony/scheduler** transport — the recurring domain jobs
(guess-reminder sweep, premium reconciliation, daily leaderboard snapshots) are dispatched
by the in-app `Schedule` provider and consumed here. There is **no cron and no separate
scheduler process** — one worker container runs both. If you add a new recurring task,
nothing changes on the box; it flows through the same worker.

The `failed` transport is Postgres-backed: inspect with
`bin/console messenger:failed:show`, retry with `messenger:failed:retry`.

## Secrets

Secrets are **not** committed and **not** SSH-provisioned. On the box, `dump_secrets`
renders an `.env` file (0600, box-only) from **Infisical**; every service reads it via
`env_file: .env`. Non-secret env (canonical `APP_URL`, `MAILER_FROM_EMAIL`, `DATABASE_HOST`)
is set inline in `compose.yaml`.

Required keys (see [`.env.prod.example`](../.env.prod.example) for the shape — the prod
values live in Infisical, not in that file):

| Variable | Purpose |
|----------|---------|
| `APP_SECRET` | Symfony session / CSRF secret |
| `DATABASE_URL` | `postgresql://…@db:5432/wtips?serverVersion=17&charset=utf8` |
| `MAILER_DSN` | SMTP for `robot@wtips.cz` (port **587** — port 465 is blocked on the box) |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default` (backs `async` + `failed`) |
| `STRIPE_SECRET_KEY` / `STRIPE_WEBHOOK_SECRET` / `STRIPE_DASHBOARD_URL` | credit purchases (see [`stripe.md`](stripe.md)) |
| `SENTRY_DSN` | error tracking |
| `POSTGRES_PASSWORD` | Postgres role password (compose refuses to start without it) |

## Health & smoke checks

- `GET /-/health-check/liveness` → `200 {"status":"ok"}` when the app + DB are reachable.
  Both the Docker healthcheck and the Traefik LB healthcheck point here; the blue-green gate
  will not flip traffic until it is green.
- After a deploy, smoke-test the public pages (landing, `/prihlaseni`, a public global
  competition) and confirm the worker is consuming: `docker compose logs -f messenger-consumer`.

## Rollback

Re-run the previous green `Release` from the GitHub UI (rebuilds that SHA and re-ships), or
on the box re-point `IMAGE_TAG` at the prior image and re-run the rollout. Data volumes
(`wtips_pgdata`, `wtips_uploads`) are `external: true`, so a rollout — or even
`docker compose down -v` — can never wipe them.
