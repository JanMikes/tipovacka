# Deployment

## Overview

Production runs as a Docker container from `ghcr.io/janmikes/tipovacka`, built
automatically by the `Release` GitHub Actions workflow after the `Tests` workflow
on `main` passes.

## Prerequisites on the target host

- Docker engine.
- A `deploy.sh` script in `/deployment/tipovacka/` that pulls the latest image
  and restarts the stack (blue/green or rolling — up to the host).
- A reachable `/-/health-check/liveness` endpoint — the blue/green gate polls
  this and expects HTTP 200 before routing traffic to a new version.

## Required environment variables on the target

| Variable | Purpose |
|----------|---------|
| `APP_ENV` | `prod` |
| `APP_SECRET` | Symfony session / CSRF secret — unique per environment |
| `DATABASE_URL` | `postgresql://user:pass@host:5432/tipovacka?serverVersion=17.0&charset=utf8` |
| `MAILER_DSN` | e.g. `smtp://user:pass@mail.example.com:587` |
| `MESSENGER_TRANSPORT_DSN` | Redis or Postgres DSN for the `async` transport |
| `SENTRY_DSN` | Sentry project DSN (optional but recommended) |

See [.env.prod.example](../.env.prod.example) for the full list.

## Required GitHub secrets

For the `deploy` job in `.github/workflows/release.yml`:

| Secret | Purpose |
|--------|---------|
| `DEPLOY_USERNAME` | SSH user on the production host. |
| `DEPLOY_PRIVATE_KEY` | Matching SSH private key (no passphrase). |

The host itself is hardcoded in the workflow as `tipovacka.thedevs.cz`; change
there if the target moves.

## Release procedure

1. Merge to `main`.
2. GitHub Actions runs the `Tests` workflow (phpunit, phpstan, cs-check,
   migrations-up-to-date). If any fails, the release aborts.
3. `Release` workflow builds and pushes the Docker image tagged with the
   commit SHA and the branch name.
4. `deploy` job SSHes into the host and runs
   `cd /deployment/tipovacka && ./deploy.sh`. The script is expected to:
   - Pull the new image.
   - Run `bin/console doctrine:migrations:migrate --no-interaction` inside the
     new container.
   - Start the new version alongside the current one (blue/green).
   - Poll `GET /-/health-check/liveness` until it returns 200.
   - Switch the reverse proxy over.
   - Stop the old version.

## Rollback

Re-run the previous `Release` workflow from the GitHub UI — it builds the old
SHA and redeploys.

## Messenger workers

Production should run a supervisor / systemd unit executing

```
bin/console messenger:consume async --time-limit=3600 --memory-limit=256M
```

Scale the number of worker processes with expected email + recalc volume. The
`failed` transport is backed by Postgres; inspect with
`bin/console messenger:failed:show`.

## Health checks

- `GET /-/health-check/liveness` → 200 + `{"status":"ok"}` when the application
  is reachable and the DB is responsive.
- Point the load balancer and deploy gate at this path.

## Secrets management

Do not commit `.env.local` or `.env.prod.local` — they are gitignored. Use the
host's secret store (Docker secrets, 1Password, Vault, etc.) and mount the
variables into the container at runtime.
