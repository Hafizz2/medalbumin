# Deploying MedAlbumin to medalbumin.com

The domain is hosted on a LiteSpeed shared-hosting account (yegara.com, nameservers hostns.io) — a cPanel setup. The Laravel backend serves everything on one domain:

- `https://medalbumin.com/` — public site (pricing, account, checkout)
- `https://medalbumin.com/api/v1/...` — the API the mobile app uses
- `https://medalbumin.com/admin` — admin console

## One-time server setup

1. **PHP version**: in cPanel → *Select PHP Version* (or *MultiPHP Manager*), pick **PHP 8.2+** and enable the extensions `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `zip`, `intl`, `bcmath`.
2. **Database**: cPanel → *MySQL Databases* → create database `..._medalbumin`, a user with the same name, a strong password, and add the user to the database with ALL PRIVILEGES.
3. **SSL**: cPanel → *SSL/TLS Status* → Run AutoSSL for `medalbumin.com` and `www.medalbumin.com` (Let's Encrypt). The current page on the domain is the host's placeholder; it goes away when we upload.
4. **Email**: cPanel → *Email Accounts* → create `noreply@medalbumin.com` (for login codes) and `support@medalbumin.com`.
5. **SSH access**: cPanel → *SSH Access* → import/authorize your public key. Note the username, host and port (yegara commonly uses port 22; some resellers use 2222).

## Directory layout on the server

Laravel's app code must live OUTSIDE the web root; only `public/` is served:

```
~/medalbumin/            ← the whole backend/ folder (app, vendor, routes, …)
~/public_html/           ← contents of backend/public (index.php edited to point at ~/medalbumin)
```

`deploy.sh` uploads exactly that and rewrites `index.php` paths for you.

## Deploying from this machine

```bash
# 1. Fill in the connection details once:
cp deploy/deploy.config.example.sh deploy/deploy.config.sh   # then edit it

# 2. Ship it:
bash deploy/deploy.sh
```

What the script does:

1. Builds the Vite assets locally (`npm run build` in `backend/`).
2. Installs production PHP dependencies into a clean `vendor/` (`composer install --no-dev`) — done locally so the server doesn't need Composer.
3. Uploads the app to `~/medalbumin` and the public files to `~/public_html` (rsync over SSH).
4. On the server: links storage, runs `php artisan migrate --force`, rebuilds caches.

## First deploy only — on the server

```bash
cd ~/medalbumin
cp .env.production .env        # then fill in every <...> value
php artisan key:generate
php artisan migrate --force --seed   # --seed only if you want the demo/admin seed data
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Add two cron jobs in cPanel → *Cron Jobs*:

```
* * * * *  cd ~/medalbumin && php artisan schedule:run >> /dev/null 2>&1
```

(and if you switch `QUEUE_CONNECTION` to `database`:)

```
* * * * *  cd ~/medalbumin && php artisan queue:work --stop-when-empty >> /dev/null 2>&1
```

## After the API is live

- The mobile app already points at `https://medalbumin.com` (`mobile/.env`, `eas.json` preview/production).
- Update Chapa's webhook/return URL to `https://medalbumin.com/...` in the Chapa dashboard.
- In @BotFather, set the login widget domain to `medalbumin.com`.
- Verify: `curl https://medalbumin.com/api/v1/config` returns JSON.
