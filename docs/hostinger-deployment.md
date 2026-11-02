# Laravel Deployment on Hostinger hPanel

Target: Hostinger Web or Cloud hosting through hPanel. Baseline date: October 31, 2026. This guide defines the intended deployment contract; account-specific paths, limits, and domain details must be verified before running deployment commands. It applies once the Laravel application and its locked dependencies exist.

## Runtime and service baseline

| Component | Project baseline | Verify on the selected account |
| --- | --- | --- |
| Laravel | Version 13, current tested patch within the major version | Locked dependencies and production platform requirements |
| PHP | Minimum 8.3; target 8.4 for production | Web handler, SSH PHP, Composer PHP, and cron PHP must agree |
| Composer | Version 2, using committed lockfile | Hostinger commonly exposes it as `composer2` |
| Database | MySQL 8.4 in local development and CI; Hostinger MySQL-compatible service in production | Actual server version, database size and connections, generated columns, constraints, and transaction behavior |
| Frontend | Livewire and Blade with compiled Vite assets | Build on local development or CI; production serves compiled files |
| Sessions and cache | Database-backed Laravel drivers | Tables, expiry cleanup, cache locks, and account storage usage |
| Queue | Database driver with short bounded execution | Cron frequency, execution limits, process support, and retry visibility |
| Email | Configured SMTP or transactional transport | Sender domain, deliverability, rate limits, and a successful test invitation |
| Private files | Storage outside the public web directory | Permissions, disk quota, backups, and authorized downloads |

Hostinger documents MariaDB for Web and Cloud hosting, while PostgreSQL requires a different arrangement such as VPS. The application uses Laravel's `mysql` connection as requested, with MySQL 8.4 for local development and CI. Validate against hPanel's actual MariaDB engine before production; the baseline does not depend on PostgreSQL row-level security or partial indexes. [Hostinger database engine](https://www.hostinger.com/support/1583226-which-database-management-system-is-used-at-hostinger/).

Laravel 13 requires PHP 8.3 or newer. Use a manual locked release deployment; the Hostinger Auto Installer's advertised framework version is not the project's version authority. Check both web and CLI runtime versions, since a website-specific PHP setting may differ from SSH and Composer. [Laravel release policy](https://laravel.com/docs/13.x/releases), [Hostinger PHP selection](https://www.hostinger.com/support/1575755-how-to-change-the-php-version-of-your-hostinger-hosting-plan/), [Hostinger Composer runtime](https://www.hostinger.com/support/5792082-how-to-solve-common-composer-issues-at-hostinger/).

## Account preflight

Before choosing the exact plan or deploying, confirm:

- SSH or an equivalent trusted release upload path and Composer 2 are available.
- The selected PHP branch has the required Laravel extensions plus the MySQL PDO driver. Run `composer check-platform-reqs --no-dev` against the production dependency set.
- Record `SELECT VERSION()` and SQL mode on the actual database. Run migrations and concurrency checks against MySQL locally and the actual host database before production.
- PHP process execution needed by Composer scripts and the planned scheduler is permitted. If not, use a verified off-host dependency build and a scheduler design compatible with the account; do not blindly disable host security controls.
- Verify writable private `storage` and `bootstrap/cache`, disk and inode quotas, database limits, upload limits, and cron resource budgets.
- Confirm how the domain serves a public-only directory. Do not assume that a primary domain's document root can be changed in every hPanel account.
- Confirm HTTPS, a private staging site or subdomain, notification email, and a backup restoration path.
- Verify available CPU and memory against the PRD pilot profile. The proposed performance and recovery targets are application acceptance targets, not a Hostinger plan guarantee.

Use hPanel's actual paths. The examples below use a placeholder account and domain. They are templates, not this project's live server details.

## Public and private directory layout

Serve only Laravel's `public` files. Keep `.env`, `vendor`, application source, database files, logs, private uploads, backups, and Git metadata outside the HTTP document root. [Laravel deployment entry point](https://laravel.com/docs/13.x/deployment).

Preferred layout when the domain can target the release's public directory:

```text
/home/u123456789/apps/hardware-store/releases/<release>/
    app/ bootstrap/ config/ database/ routes/ vendor/
    public/                 Domain document root
/home/u123456789/apps/hardware-store/shared/
    .env                    Stable secrets and encryption key
    storage/                Persistent private files and logs
```

If hPanel fixes the domain to `public_html`, use a tested split layout:

```text
/home/u123456789/apps/hardware-store/<release>/
    Laravel application and dependencies
/home/u123456789/apps/hardware-store/shared/
    .env and persistent private storage
/home/u123456789/domains/example.tld/public_html/
    index.php, .htaccess, build/, and other intentional public files only
```

For the split layout, the deployed public entry point must load the matching private release's Composer autoloader, bootstrap file, and maintenance marker. Configure Laravel's public path consistently for both web and Artisan, so Vite manifest discovery, generated assets, and package behavior resolve to the same public directory. Implement and test this adapter during the foundation milestone before selecting the split layout for production. Include hidden `.htaccess` files when uploading public artifacts.

Use a document-root setting or symlink only after confirming account support. Do not copy the entire application into `public_html` and rely on a single rewrite rule to keep private files safe. Private attachments are served through authorized application responses; `storage:link` is not required for merchant documents.

## Build and release preparation

On local development or CI, install from committed lockfiles, run tests against MySQL, and compile assets:

```sh
composer install --no-interaction --prefer-dist
npm ci
npm run build
php artisan test
```

Produce a release artifact containing application code, `composer.json`, `composer.lock`, the built `public/build` directory and manifest, and other intentional public assets. Install production PHP dependencies with Composer 2 on the host, or include a production `vendor` directory built in a matching Linux/PHP environment. Do not upload `node_modules`, a development `public/hot` marker, development databases, test credentials, or a local `.env` file.

Keep the server installation tied to the lockfile:

```sh
composer2 install --no-dev --prefer-dist --no-interaction --optimize-autoloader
composer2 check-platform-reqs --no-dev
```

Use the verified PHP binary explicitly when the account's default PHP differs. Do not run `composer update` on production. Build assets before upload; no Node process, Vite development server, or server-side rendering service is required for this interface. Hostinger documents Composer 2 access for supported Web and Cloud plans. [Hostinger Composer](https://www.hostinger.com/support/5792078-how-to-use-composer-at-hostinger/).

## Production configuration

Create the production environment on the host using secure provisioning. Example nonsecret settings:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.tld
APP_TIMEZONE=UTC
DB_CONNECTION=mysql
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
```

`APP_TIMEZONE` must be wired through application configuration if it is not present in the scaffold. Business calendar filters use each Store's saved timezone; server time remains UTC. Configure the actual database host, name, user, password, mail transport, and `APP_KEY` privately. Do not put these values in Git or command logs.

Generate the application key once during initial secure setup and preserve it across releases. Rotating it without a migration plan can invalidate encrypted TOTP secrets and other encrypted data. Keep application access to environment values in Laravel config files so configuration caching works correctly.

Use secure sessions, HTTPS, private file permissions, and least-privileged account capabilities. Exclude `/admin`, `/store`, login and recovery routes, every Livewire route, and private JSON or download responses from full-page caching. Livewire 4 may use a generated `/livewire-.../` prefix; inspect the actual route list instead of excluding only `/livewire/*`. Authenticated responses should send appropriate private or no-store cache controls. Test this exclusion with two store identities.

## Deployment sequence

1. Verify the release artifact, its commit ID, built assets, runtime, database engine, and lockfile platform requirements.
2. Take a recoverable database and private file backup; record the existing release and persistent storage paths.
3. Pause or gate scheduled queue processing and obtain a deployment lock. Stop new financial writes with maintenance mode before a non-atomic release switch.
4. Upload or install the new private release without overwriting persistent `.env` or private storage. Prepare its matching public artifact.
5. Run reviewed migrations with the verified production PHP binary. Do not run sample seeders, `migrate:fresh`, or test data creation on production.
6. Clear stale release-specific caches and rebuild configuration, route, and view caches for the target environment. Confirm commands work with the selected public-path adapter.
7. Switch application and public assets as one controlled release, or keep maintenance mode active while both are replaced.
8. Run smoke checks before re-enabling writes; then resume cron and monitor failed jobs and queue delay.

Typical Artisan commands, executed from the correct release with the verified PHP binary, are:

```sh
php artisan down
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

These are steps within the release process, not a complete deploy script. `down` and `up` must use shared maintenance state or the correct live release during a switch. Protect application cache locks and authorization cache invalidation when clearing caches. Verify release switch and scheduler exclusion behavior before automating deployment.

## Scheduler and bounded queues

Use one hPanel Custom cron entry to invoke Laravel's scheduler at the verified available interval, targeting one minute when supported. The Laravel schedule launches short non-overlapping outbox and queue work. hPanel cron schedule fields use UTC. [Hostinger cron setup](https://www.hostinger.com/support/1583465-how-to-set-up-a-cron-job-at-hostinger/).

Example command with placeholder paths:

```sh
/opt/alt/php84/usr/bin/php /home/u123456789/apps/hardware-store/current/artisan schedule:run
```

The `current` alias and PHP path must exist on the actual account. A bounded queue invocation can use `queue:work --stop-when-empty --max-time=45 --tries=3`. Test process support, job timeout enforcement, database `retry_after`, and the host's execution limit before adopting these illustrative numbers. Maximum worker time is not a hard timeout for a job already running. [Laravel queue execution controls](https://laravel.com/docs/13.x/queues).

Use shared database locks to prevent overlapping drains and deployments. Jobs must set verified store context, clear it afterward, and be safe to retry after a host termination. Expose pending invitation delivery and failed-job state to the admin. Notifications are eventually delivered after a measured scheduling delay; checkout never waits for notification delivery.

If a cron command needs shell redirection or compound shell syntax, place that logic in a private reviewed `.sh` file and schedule that script. hPanel's command field may reject special characters. Do not expose an unauthenticated web endpoint for queue or scheduler execution. [Hostinger cron command syntax](https://www.hostinger.com/support/5646919-how-to-set-up-a-cron-job-with-special-characters-at-hostinger/).

## Backups recovery and rollback

Maintain database, private storage, and required encryption key recovery under a defined retention policy. Host backups alone must not be assumed to meet the PRD recovery targets; measure restoration and consider an independent encrypted backup copy.

Rollback application code only if it is compatible with the migrated schema. Never blindly run migration rollback against live merchant postings. Prefer compatible forward fixes; if restoration is required, assess transactions posted since the backup before restoring. Preserve financial history and source links.

Recover a single store from a shared backup in an isolated environment with scoped extraction and current authorization rules. Rehearse this before onboarding real merchant data. Document the accepted recovery point, recovery time, backup age, and operational owner.

## Deployment verification

- Laravel health and a separate authenticated readiness check confirm application boot, database access, storage, and expected schema without revealing secrets.
- Authentication works over HTTPS; public registration is absent; platform MFA is enforced.
- Store A cannot read Store B's records, files, cached pages, Livewire resources, or exports.
- Vite assets load without a development server, stale hot marker, or manifest error.
- `.env`, `.git/config`, `composer.json`, logs, private storage, and application source are not accessible as public files.
- MySQL-compatible guard indexes reject duplicate active owners and open sessions, and transactions preserve posting integrity.
- A scheduled test job runs and exits, respects overlap locks, and clears store context before a second-store job.
- A test invitation is delivered through the configured transport and failures are visible.
- Sale, refund, expense, cash close, and matched admin/store reports pass in staging before real operation.
- Backups, monitored cron execution, failed jobs, disk usage, and resource alerts have named operational ownership.

## Capacity changes

If bounded jobs cannot keep up, reports exceed verified limits, or host capabilities cannot enforce the deployment contract, reassess the exact Cloud plan, external managed services, or a later VPS move. A change in hosting does not relax tenant isolation, idempotency, financial consistency, or backup requirements. Record measured constraints and the resulting decision in the [decision register](decisions-and-research.md).

## Production layout for wgalas

The October 31, 2026 repair of `wgalas.lzrk.io` uses the split layout:

| Location | Purpose |
| --- | --- |
| `/home/u589081721/domains/wgalas.lzrk.io/laravel` | Git checkout, Artisan, dependencies, private storage, and production environment |
| `/home/u589081721/domains/wgalas.lzrk.io/public_html` | Public entry point, `.htaccess`, compiled assets, and intentional static files |
| `/home/u589081721/domains/wgalas.lzrk.io/.deployment-backups/403-20261031T010334Z` | Original environment, public entry files, and bootstrap cache from before the repair |

Run Git, Composer, and Artisan commands from the private `laravel` directory. The domain previously served the entire checkout without a root index, producing LiteSpeed's directory-level 403. The repaired public entry point loads the sibling `laravel` application, and `.htaccess` explicitly selects `index.php` while retaining Laravel's front-controller rules.

The reusable [Hostinger entry template](../deployment/hostinger/public-index.php) must be copied to `public_html/index.php`; it is a deployment template, not the entry point used by a normal local Laravel checkout. Keep `public/index.php` in the source checkout unchanged for normal Laravel operation. After the normal release preflight and asset build, the public-file copy step for this layout is:

```sh
rsync -a --exclude=/index.php public/ ../public_html/
cp deployment/hostinger/public-index.php ../public_html/index.php.next
php -l ../public_html/index.php.next
mv ../public_html/index.php.next ../public_html/index.php
{ printf 'DirectoryIndex index.php\n'; cat public/.htaccess; } > ../public_html/.htaccess
```

Retain `APP_PUBLIC_PATH=/home/u589081721/domains/wgalas.lzrk.io/public_html`, `APP_ENV=production`, `APP_DEBUG=false`, and `SESSION_SECURE_COOKIE=true`. The repair preserved the existing application key and database configuration. Existing migrations and seed data were retained.

SSH provided PHP 8.3 and Bun but no Node or npm at repair time. The attempted Bun build could not launch the Node-based build tool, so the matching, previously verified frontend artifact was uploaded from the local build. Future releases should build from their own locked source commit in local development or CI rather than reuse an older artifact.

Verification after repair: homepage, login, health endpoint, and compiled stylesheet returned 200; protected portals redirected to login; public registration returned 404; sensitive environment, Git, and log paths were denied; the browser rendered the login screen without console errors. This check covers deployment and anonymous access, not an authenticated merchant transaction flow.

## Operational pilot release

Use [the release notes](release-notes.md) and [user guide](user-guide.md) alongside this runbook. Operational posting is synchronous and does not depend on a background queue. The current host has one existing administrator with TOTP enrolled and no configured SMTP transport. The release preserves that identity and uses privately shared activation/recovery links for store owners.

`php artisan app:backup` creates a private timestamped directory under `storage/app/backups/` containing a compressed consistent database dump, private attachments, the environment needed for key recovery, and SHA-256 checksums. The directory is mode 700; files are mode 600. A temporary MySQL options file keeps credentials out of process arguments and is removed afterward. A snapshot without `manifest.json` is incomplete. New snapshots also verify all payload checksums, gzip readability, and the private-file archive before success. Run `php artisan app:backup-verify` to reverify the latest snapshot or pass an explicit path under `storage/app/backups`. The verification marker is bound to the manifest; this verifies integrity, not recovery time or restored database semantics. Keep backups outside `public_html`, monitor disk use, and protect any independent copy. No automatic deletion policy is imposed by this release.

The Laravel schedule includes bounded queue processing every minute and backups daily at 02:00 Asia/Manila. SSH on this host does **not** provide a `crontab` command, so enabling recurring execution requires an hPanel Custom cron entry:

```text
Minute: *
Hour: *
Day: *
Month: *
Weekday: *
Command: /bin/sh /home/u589081721/domains/wgalas.lzrk.io/scheduler.sh
```

Copy `deployment/hostinger/scheduler.sh` to `/home/u589081721/domains/wgalas.lzrk.io/scheduler.sh`, outside the public directory. The wrapper changes to the private application and redirects output to its scheduler log; hPanel receives no shell operators. This Custom cron entry was saved in hPanel on November 2, 2026; automatic heartbeat and queue execution were verified at 11:17:01 Asia/Manila. `/usr/bin/php` resolves to PHP 8.3.36. The scheduler writes a heartbeat every minute; verify freshness under Admin → System status. Backups run at 02:00 Asia/Manila. Sales, stock, reports, invitations, and manual recovery links work without cron. Rotate the scheduler log and review backup disk use as part of hosting maintenance.

Release procedure for this existing host: take and validate a private pre-release backup, enable maintenance, fast-forward the private checkout to the reviewed commit, install the matching compiled frontend, run additive migrations, rebuild configuration/routes/views, verify the runtime with a rollback-only synthetic transaction, then leave maintenance and verify HTTPS routes/assets. Do not reseed or regenerate the application key. Keep the preexisting untracked `bun.lock`; it is not a reason to overwrite application files.

For recovery, restore the database into an isolated database first, restore private attachments and the matching encryption key, and verify tenant relationships, stock/cash reconciliation, and a known record before considering a live switch. Never automatically import an older snapshot over new merchant transactions. Rollback code only when compatible with the migrated schema; additive pilot tables can remain during a code rollback. Off-site copies and a full timed disaster-recovery drill remain operator follow-ups.

### Hostinger automatic Git deployment

The release push triggered an additional Hostinger checkout directly into `public_html`. This was detected by the final exposure check, and the extra source tree was moved into the private release backup before restoring a public-only directory. The private application remained in `laravel`.

The repository now includes a root `.htaccess` safety guard, and `public/.htaccess` denies source directories, hidden files, and direct PHP execution outside `index.php`. Only the intentional static allowlist and Laravel routes are served. This protects against a repeat checkout, but automatic source deployment into the document root is still the wrong release target. On November 2, 2026, **Deployments → Auto-deployment → Turn off auto-deployment** was applied and hPanel confirmed it is off. Keep it disabled until a release pipeline explicitly targets the private `laravel` directory and performs migrations, public-asset installation, and verification. Do not use the hPanel Redeploy button while its target remains `public_html`.

After any Git push, check both the private application revision and the public directory contents. Never assume a successful Git webhook has migrated the database or installed the matching built assets.
