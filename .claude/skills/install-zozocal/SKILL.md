---
name: install-zozocal
description: Install the ZozoCal restaurant reservation app (PHP 8.3 + Apache + PostgreSQL 17) on a new Linux server, either as a fresh install or by moving an existing install with its data. Use when asked to install, deploy, set up, migrate or move this repository to a server, or to check that an install is healthy.
---

# Install ZozoCal on a server

This runbook takes a bare Ubuntu server to a working ZozoCal install. Both people and agents can follow it.
Work from the repository root. Every step ends with a check; do not move on until it passes.
The last step, `php scripts/check-install.php`, is the definition of done.

## Ground rules

- **Ask before anything destructive or hard to undo.** That means dropping or overwriting a
  database, replacing a non-empty `/var/www`, overwriting an existing `config/local.php`,
  disabling an existing Apache site, or replacing a user's crontab.
- **Never print, log or commit secrets.** That covers database passwords, `config/local.php`
  contents, API keys and dump files. Generate passwords with `openssl rand -base64 24`,
  write them straight into `config/local.php`, and tell the human where they are rather than
  what they are.
- **Some steps belong to the human.** DNS records, TLS certificates for a real domain, and
  anything in the Google, Retell, Twilio or MailerSend consoles. Prepare exact values for
  them, hand them over, and wait.
- `config/local.php` is git-ignored. Never add it to git.
- Only load `docs/sql/pg_schema.sql`, `docs/sql/nav_permissions.sql` and `docs/sql/os_adoption.sql`. The other files in
  `docs/sql/` are old MySQL-era migrations and will fail on PostgreSQL.

## 0. Collect these first

Ask the human for anything not already given:

| Value | Example | Notes |
|---|---|---|
| Mode | fresh / move | "move" copies the database from an existing server |
| Domain | `reservations.example.com` | DNS A record must point at this server before HTTPS |
| Repository URL | `git@github.com:maludb-ed/ZozoCal-Restaurant.git` | Private repo: the server needs a deploy key or token |
| Install path | `/var/www` | The paths in `docs/deploy/*` assume `/var/www` |
| Database name / user | `zozocal` / `zozocal` | |
| Fresh only: first admin | email, first and last name, restaurant name, time zone, slug | The admin types their own password |
| Move only: old server | SSH access or a `pg_dump -Fc` file | Plus the old `config/local.php` values (Google keys, Retell fallback) |

## 1. System packages (Ubuntu 24.04)

```bash
sudo apt update
sudo apt install -y apache2 libapache2-mod-php8.3 php8.3-cli php8.3-pgsql php8.3-curl \
  php8.3-mbstring php8.3-xml git unzip curl composer
# PostgreSQL 17 from the PGDG repository
sudo apt install -y postgresql-common && sudo /usr/share/postgresql-common/pgdg/apt.postgresql.org.sh -y
sudo apt install -y postgresql-17
```

Check: `php -v` shows 8.3, `php -m | grep pdo_pgsql`, `psql --version` shows 17.
(Other distributions work too: you need PHP ≥ 8.1 with those extensions, Apache with mod_php or
PHP-FPM, and PostgreSQL ≥ 15. The app has been tested on 8.3 and 17.)

## 2. Get the code

```bash
sudo mkdir -p /var/www && sudo chown "$USER":www-data /var/www
git clone <repository URL> /var/www          # /var/www must be empty — ask if it is not
cd /var/www
composer install --no-dev --optimize-autoloader
```

Check: `vendor/autoload.php` exists.

## 3. Database

Create the role and database. Put the generated password straight into `config/local.php`
in step 4; don't echo it.

```bash
DB_PASS="$(openssl rand -base64 24 | tr -d '/+=')"
sudo -u postgres psql -v ON_ERROR_STOP=1 \
  -c "CREATE ROLE zozocal LOGIN PASSWORD '$DB_PASS'" \
  -c "CREATE DATABASE zozocal OWNER zozocal"
```

**Fresh install:** load the schema as the app user, so it owns the tables:

```bash
export PGPASSWORD="$DB_PASS"
psql -h 127.0.0.1 -U zozocal -d zozocal -v ON_ERROR_STOP=1 -f docs/sql/pg_schema.sql
psql -h 127.0.0.1 -U zozocal -d zozocal -v ON_ERROR_STOP=1 -f docs/sql/nav_permissions.sql
psql -h 127.0.0.1 -U zozocal -d zozocal -v ON_ERROR_STOP=1 -f docs/sql/os_adoption.sql
```

**Move:** see "Moving an existing install" below instead of loading the schema.

Check: `psql ... -Atc "select count(*) from information_schema.tables where table_schema='public'"` returns 51.

## 4. Configuration

```bash
cp config/local.example.php config/local.php     # ask first if config/local.php exists
chmod 640 config/local.php && sudo chgrp www-data config/local.php
```

Edit `config/local.php`:

- `DB_*`: the values from step 3.
- `APP_URL`: `https://<domain>` with no trailing slash. It is used for the URLs registered with Retell.
- `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`: optional, for Google sign-in.
- `RETELL_DEFAULT_API_KEY`: optional. It is an account-wide fallback when a restaurant has no Retell key of its own.

Any key can instead be an environment variable (for example `SetEnv` in the vhost).

## 5. File permissions

```bash
sudo chgrp -R www-data /var/www/logs && sudo chmod -R g+w /var/www/logs
```

Diagnostic pages in the web root expose phpinfo and database details. On a public server, ask
the human whether to delete them: `html/info.php`, `html/test-db.php`, `html/test-email.php`.

## 6. Apache and HTTPS

```bash
sudo cp docs/deploy/apache-vhost.conf /etc/apache2/sites-available/zozocal.conf
sudo sed -i 's/your-domain.example/<domain>/' /etc/apache2/sites-available/zozocal.conf
sudo a2ensite zozocal
sudo a2dissite 000-default          # ask first if 000-default serves anything else
sudo apachectl configtest && sudo systemctl reload apache2
```

HTTPS needs DNS pointing at this server first (human). Then:
`sudo apt install -y certbot python3-certbot-apache && sudo certbot --apache -d <domain>`.

No mod_rewrite or `.htaccess` is needed. Bearer tokens are read with `getallheaders()`, so
mod_php needs no special Authorization handling.

Check: `curl -s -o /dev/null -w '%{http_code}' https://<domain>/login.php` returns 200.

## 7. Scheduled jobs

```bash
sudo crontab -u www-data -l 2>/dev/null     # if this prints entries, merge by hand instead
sudo crontab -u www-data docs/deploy/crontab
```

This schedules invoice generation and overdue marking (daily), guest reminders (hourly) and
outbound voice messages (every minute). Edit paths if the app is not in `/var/www`.

## 8. First platform admin (fresh install only)

```bash
php scripts/create-super-admin.php --email=<email> --first=<first> --last=<last> \
  --restaurant="<restaurant name>" --slug=<slug> --timezone=<IANA zone, e.g. America/New_York>
```

The script asks for the password without showing it; hand the terminal to the human for it.
For automation use `--password-stdin` with a password the human supplies. The new restaurant
gets default settings, a Main Dining section, turn times and opening hours.
Running the script with an existing email promotes that user instead.

## 9. Verify

```bash
php scripts/check-install.php --url=https://<domain>     # must end with no FAIL
php scripts/api-smoke.php https://<domain>               # agent API, REST + MCP; must pass
```

Then ask the human to sign in at `https://<domain>/login.php` and open a few sidebar pages.

## 10. Hand-off: external services (human, in each console)

Give the human this list with the real domain filled in:

- **Google sign-in:** add `https://<domain>/google-callback.php` as an authorized redirect URI.
- **Restaurant keys:** each restaurant admin enters its keys in the app. Retell, the guest MCP key and OpenAI (for the SMS and email agents) go under Settings → Integrations; SMS/Twilio and MailerSend go under Settings → Notifications. After a move they come across with the database.
- **Twilio (SMS agent):** set each number's incoming-message webhook to `https://<domain>/api/sms/webhook.php`.
- **Inbound email agent:** point the provider's inbound webhook at `https://<domain>/api/email/webhook.php`.
- **Retell voice agents (after a domain change):** in Settings → Voice Prompts, edit each agent so its webhook and MCP URLs use the new domain, and save. Then push the full configuration to Retell with the `voice_agents_sync` API operation. The page's "Update Prompt in Retell" button only pushes the prompt text. New agents pick up `APP_URL` automatically. The guest MCP server only accepts the restaurant's MCP key, so agents created without it must be re-created.
- **AI agents that administer the app:** create a key in Settings → API Keys. Connect over MCP at `https://<domain>/api/mcp/admin.php`, or REST at `https://<domain>/api/v1/index.php/` (spec at `/openapi.json`). See `docs/api.md`.

## Moving an existing install

Do steps 1–2 and create the role and database from step 3 on the new server, then:

1. **Stop the old server's writes and jobs.** Otherwise invoices and reminders can go out
   twice. Ask the human to schedule this.
   - On the old server, remove or comment out the www-data crontab: `sudo crontab -u www-data -e`
   - Put the old site in maintenance, or accept that bookings made after the dump are lost.
2. **Dump the old database** (on the old server):
   `pg_dump -Fc -h 127.0.0.1 -U <old user> <old db> > zozocal.dump`
3. **Copy the dump over** with `scp`, then restore it as the new app user:
   ```bash
   pg_restore -h 127.0.0.1 -U zozocal -d zozocal --no-owner --no-privileges zozocal.dump
   ```
   Warnings about missing roles are fine. Errors about tables are not.
4. **Bring the old schema up to date if needed.** Compare with `docs/sql/pg_schema.sql`.
   `check-install.php` reports an old `api_tokens` table; add any missing columns with `ALTER TABLE`.
   Then load `docs/sql/os_adoption.sql` (safe to run again).
5. **Copy the old per-server values** into the new `config/local.php`: Google keys and the
   Retell fallback. The database settings are the new server's. Set `APP_URL` to the new domain.
6. **Skip step 8.** The users come with the database. Do steps 4–7 and 9–10.
7. **Switch DNS to the new server** (human). Then delete `zozocal.dump` from both servers, because it contains guest data.

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| "Database connection failed" | `DB_*` in `config/local.php`; `sudo systemctl status postgresql`; PostgreSQL must accept password logins on 127.0.0.1 (default `pg_hba.conf` does) |
| Empty sidebar after login | `nav_permissions` not loaded — run `docs/sql/nav_permissions.sql` |
| 500 errors | `sudo tail /var/log/apache2/zozocal-error.log` |
| Nothing in `logs/`, or webhook 500s | `logs/` not writable by www-data (step 5) |
| API always 401 with a valid key | Key revoked or expired, or the user deactivated in that restaurant; create a new key |
| `/api/v1/index.php/...` 404 from Apache | `AcceptPathInfo Off` somewhere; PATH_INFO must be allowed |
| Voice agents stopped answering after a move | Retell still points at the old domain (step 10) |

## Report back

Finish with a short summary for the human:

- the URL
- mode (fresh or move)
- the `check-install.php` result, with any WARN lines
- where the secrets are (`config/local.php`), without their values
- the step 10 checklist, with the domain filled in
