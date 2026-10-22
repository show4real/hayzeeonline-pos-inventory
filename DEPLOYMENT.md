# Deploying the Hayzeeonline POS API

Production runs as a small Docker Compose stack on a VPS, behind the Apache
instance already on that box. GitHub Actions builds the images, pushes them to
GHCR, copies three files to the server and runs the deploy script there.

Nothing is built on the server, and no configuration is edited by hand on it.

```
                  ┌─────────────────────── the VPS ───────────────────────┐
  browser ──TLS──▶ Apache :443  ──http──▶  nginx  ──fastcgi──▶  app       │
                  │  (certbot)   127.0.0.1:8092   │            (php-fpm)  │
                  │                               │                │      │
                  │                          uploads volume ───────┘      │
                  │                                                │      │
                  │                               redis ◀──────────┘      │
                  └───────────────────────────────────────────────────────┘
                                                        │
                                            MySQL (external, via .env)
```

* `pos-api.hayzeeonline.com` → Apache → `127.0.0.1:8092` → the stack.
* The published port is bound to loopback, so Apache is the only way in.
* The database is **external**: there is no `db` container in production. It is
  reached through `DB_HOST` in the `.env` secret.

---

## 1. What you need once, before the first deploy

### 1.1 DNS

Point the name at the VPS:

```
pos-api.hayzeeonline.com.   A   <vps-ip>
```

Do this *before* running certbot — the HTTP-01 challenge resolves the name.

Note that the POS **UI** lives at `pos.hayzeeonline.com`. The API is a
separate name on purpose, so the two can move independently.

### 1.2 On the VPS

```bash
# Docker, if it is not there already
curl -fsSL https://get.docker.com | sh

# Apache with the modules the vhost needs. provision-apache.sh enables them,
# but Apache itself has to exist.
sudo apt-get install -y apache2
sudo apt-get install -y certbot python3-certbot-apache

# Where the deploy lands. The workflow's scp step creates it, but creating it
# first lets you check the permissions.
sudo mkdir -p /srv/hayzee-pos-api
sudo chown "$USER" /srv/hayzee-pos-api
```

The deploy user must be able to `sudo`, because `provision-apache.sh` writes
the vhost and reloads Apache.

**Check the port is free before the first deploy.** The workflow publishes
`127.0.0.1:8092`. If this box also hosts the hamzeal inventory stacks they
hold 8090 and 8091:

```bash
sudo ss -ltnp | grep '127.0.0.1:809'
```

If 8092 is taken, change `PROXY_PORT` in `.github/workflows/main.yml` — it is
the single source of truth and drives the published port, the health check and
the vhost together.

### 1.3 GitHub secrets

Repository → Settings → Secrets and variables → Actions. The workflow also
references an `environment: production`, so these can live on that environment
instead if you prefer approval gates.

| Secret | What it is |
| --- | --- |
| `VPS_HOST` | Server hostname or IP |
| `VPS_USER` | SSH user (must be able to `sudo`) |
| `VPS_PASSWORD` | That user's password — used for SSH **and** for `sudo -S` |
| `VPS_PORT` | SSH port. Optional; defaults to 22 |
| `ENV_PRODUCTION` | The entire production `.env`, pasted in |

`GITHUB_TOKEN` is minted per run — there is no registry secret to create.

Everything that is *not* secret (domain, port, stack name, deploy path) lives
in the `env:` block at the top of `.github/workflows/main.yml`. Change it
there, not in the UI.

### 1.4 The `ENV_PRODUCTION` secret

Paste a complete `.env`. The workflow refuses to deploy if `APP_KEY`,
`DB_HOST`, `JWT_SECRET` or `APP_TIMEZONE` are missing, or if it still uses
pre-Laravel-11 key names — those fail silently rather than loudly, so they are
caught in CI instead.

```dotenv
APP_NAME="Hayzeeonline POS"
APP_ENV=production
APP_KEY=base64:<php artisan key:generate --show>
APP_DEBUG=false

# Must match DOMAIN in the workflow. The 'public' filesystem disk builds
# product image URLs from it, so getting it wrong means broken images.
APP_URL=https://pos-api.hayzeeonline.com

# Africa/Lagos, not UTC. See §5.
APP_TIMEZONE=Africa/Lagos

# Existing password hashes were made at cost 10. Laravel rehashes on login
# when this disagrees, which is work for nothing.
BCRYPT_ROUNDS=10

# stderr, so `docker logs` is the one place to look.
LOG_CHANNEL=stderr
LOG_LEVEL=warning

DB_CONNECTION=mysql
# host.docker.internal resolves to the VPS itself — the compose file maps it.
DB_HOST=host.docker.internal
DB_PORT=3306
DB_DATABASE=inventory_hayzeeonline_api
DB_USERNAME=<user>
DB_PASSWORD=<password>

# Laravel 11 names. CACHE_DRIVER / MAIL_DRIVER / BROADCAST_DRIVER are read by
# nothing and the workflow rejects them.
CACHE_STORE=redis
BROADCAST_CONNECTION=null
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log

# php artisan jwt:secret --show
JWT_SECRET=<secret>
# Minutes. 216000 = 150 days, which is what the app has always used; the UI
# reads it back as `expire_in` on login.
JWT_TTL=216000

# Apache is the only thing that can reach the container, so '*' is safe.
TRUSTED_PROXIES=*
```

`CACHE_STORE=redis` matters more than it looks: the API's rate limiter
(`throttle:60,1`) counts in the cache. On the file store those counters would
be per-container and would reset on every deploy.

---

## 2. Deploying

Tag and push:

```bash
git tag v1.0.0
git push origin v1.0.0
```

Only `vX.Y.Z` triggers the workflow. `workflow_dispatch` runs it from the
Actions tab against `main`.

The run does, in order:

1. **Tests** — `php artisan test` on PHP 8.2 against in-memory SQLite. A
   release that cannot pass its own tests never reaches the registry.
2. **Build & push** the `app` image, then the `nginx` image *from* it, so both
   halves always carry the same `public/` assets. Tagged with the version, the
   commit SHA and `latest`.
3. **Write `.env`** from the secret, with the sanity checks above.
4. **Copy** `.env`, `docker-compose.prod.yml` and `docker/` to the server.
5. **Run `docker/deploy.sh`**, which pulls both images, runs
   `artisan migrate --force` once in a throwaway container, rolls the stack,
   and polls `http://127.0.0.1:8092/up` for 40s. **If it never answers, the
   previous images are put back.**
6. **Run `docker/provision-apache.sh`**, which writes and enables the vhost if
   it is not already serving the name.

### TLS, once

`provision-apache.sh` deliberately does not touch certificates. After the
first successful deploy:

```bash
sudo certbot --apache -d pos-api.hayzeeonline.com
```

Certbot copies the `:80` vhost to `pos-api.hayzeeonline.com-le-ssl.conf`, adds
the certificate directives and turns the original into a redirect. Later
deploys leave that alone: the script never rewrites a vhost file that already
exists, precisely so it cannot undo this.

---

## 3. Rolling back

Re-run the workflow for the older tag from the Actions tab, or on the server:

```bash
cd /srv/hayzee-pos-api
APP_IMAGE=ghcr.io/show4real/hayzeeonline-pos-inventory-app:v1.0.0 \
NGINX_IMAGE=ghcr.io/show4real/hayzeeonline-pos-inventory-nginx:v1.0.0 \
STACK_NAME=hayzee_pos_api APP_PORT=8092 \
  docker compose -f docker-compose.prod.yml up -d
```

Migrations are not rolled back. A release that changes the schema needs a
deliberate `migrate:rollback`.

---

## 4. Local development

The local stack is a *different* set of files, in the parent directory of this
repository — `../docker-compose.yml`, `../php/`, `../nginx/`. It bind-mounts
the code instead of baking it in, and runs its own MySQL.

```bash
cd ..                      # the directory holding docker-compose.yml
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan migrate
```

| Service | URL |
| --- | --- |
| API | http://localhost:8093 |
| phpMyAdmin | http://localhost:3705 |
| MySQL | `127.0.0.1:3313` (`DB_PORT`) |

Those ports are unusual because this machine runs sixteen other Compose
stacks. Every one is overridable:

```bash
NGINX_PORT=9099 DB_PORT=3399 docker compose up -d
```

php-fpm is **not** published — nginx reaches it over the private compose
network. The previous version of the compose file mapped `9002:9002`, which
claimed a host port and never worked, since php-fpm listens on 9000.

---

## 5. Things that will bite you

**Dates are naive Africa/Lagos time.** Timestamps in this database were
written as local time with no zone. `App\Concerns\SerializesDatesForApi` keeps
them serializing as `Y-m-d H:i:s`, which is what Laravel 6 did and what the
POS UI parses with `moment()`. Laravel 7 changed the default to ISO-8601 in
UTC. If that trait is removed, or `APP_TIMEZONE` is changed, every timestamp
shifts an hour and anything stored between 00:00 and 01:00 renders as the
previous day — wrong dates on invoices, stock receipts and payments. The
workflow checks `APP_TIMEZONE` for this reason.

**A migration for a table that already exists must be guarded.**
`2022_05_25_155329_create_creditors_payments_table` creates a table that is
already in the database but was never recorded as migrated. Unguarded, it
fails with `1050 Table 'creditors_payments' already exists`, and because
`deploy.sh` treats a failed migration as a failed release, *no deploy would
ever succeed*. It is wrapped in `Schema::hasTable()`. Do the same for any
future migration of an out-of-band table.

**Class names must match their filenames exactly.** The production image
builds the autoloader with `--classmap-authoritative`, and its filesystem is
case-sensitive. A reference like `use App\product;` works on a developer's Mac
and fails here. The upgrade fixed three of these; `composer dump-autoload
--classmap-authoritative` warns about the rest.

**`public/error_log` and `hayzee_latest.sql` are committed to this
repository.** Both are excluded from the images by `.dockerignore`, and
nginx denies `/error_log` explicitly — it has no extension, so an
extension-based rule misses it. They should still come out of git history: the
dump is a 3.8MB copy of production data, and the log leaks absolute server
paths and stack traces.

**Password recovery is broken, and was before this upgrade.** The three
recovery routes named `Api\ForgotPasswordController`, which exists in no
commit in this repository. They are commented out in `routes/api.php` with the
details. The POS UI still calls `sendrecovery`.

**Four purchase-order routes call methods that do not exist** —
`getSalesOrder`, `saleOrder`, `multSaleOrder` and `delete` on
`PurchaseOrderController`. They are registered exactly as they were before and
still fail when called. See the note in `routes/api.php`.

---

## 6. Troubleshooting

```bash
cd /srv/hayzee-pos-api

docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs -f --tail=100 app
docker compose -f docker-compose.prod.yml logs -f --tail=100 nginx

# Does the stack answer behind the proxy?
curl -i http://127.0.0.1:8092/up

# Is Apache actually serving the name?
sudo apache2ctl -S | grep pos-api

# What is running, and from which commit?
docker inspect --format '{{index .Config.Labels "org.opencontainers.image.version"}}' hayzee_pos_api_app
```

| Symptom | Where to look |
| --- | --- |
| `deploy: never became healthy` | `logs app` — almost always `.env`: a wrong `DB_HOST`, or a missing `APP_KEY`/`JWT_SECRET`, which the entrypoint refuses to start without |
| Apache's default page instead of the API | `apache2ctl -S`; another vhost may claim the name, or the site is disabled |
| 502 from Apache | the stack is down, or `PROXY_PORT` and the vhost disagree |
| `1050 Table ... already exists` | an unguarded migration — see §5 |
| Product images 404 | `APP_URL` does not match the real hostname, or the `uploads` volume is missing from the nginx service |
| Dates off by an hour, or a day early | `APP_TIMEZONE`, or `SerializesDatesForApi` was removed — see §5 |
