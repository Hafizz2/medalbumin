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

## Deploying with git (the normal way)

The code lives at `https://github.com/Hafizz2/medalbumin.git`; Vite assets are committed, so the server never needs Node. If the repo is private, clone with a GitHub token (`https://<token>@github.com/Hafizz2/medalbumin.git`) or use cPanel → *Git Version Control*.

### First deploy — on the server (cPanel Terminal or SSH)

```bash
cd ~
git clone https://github.com/Hafizz2/medalbumin.git app

# PHP dependencies (cPanel ships composer; otherwise: curl -sS https://getcomposer.org/installer | php)
cd ~/app/backend
composer install --no-dev --optimize-autoloader

# Environment
cp .env.production .env
nano .env                      # fill every <...>: DB, mail password, Chapa/Telegram keys
php artisan key:generate
php artisan migrate --force --seed   # --seed only if you want the demo/admin seed data

# Wire the web root (public_html is the docroot of medalbumin.com)
cp -a ~/app/backend/public/. ~/public_html/
sed -i "s|__DIR__.'/../|'$HOME/app/backend/'.'|g" ~/public_html/index.php
ln -sfn ~/app/backend/storage/app/public ~/public_html/storage

# Caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Updating after a new push

```bash
cd ~/app && git pull
cd backend && composer install --no-dev --optimize-autoloader
php artisan migrate --force
cp -a ~/app/backend/public/. ~/public_html/
sed -i "s|__DIR__.'/../|'$HOME/app/backend/'.'|g" ~/public_html/index.php
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## Alternative: rsync from this machine

`deploy/deploy.sh` pushes the working tree straight over SSH (no git on the server needed): copy `deploy.config.example.sh` to `deploy.config.sh`, fill in the SSH details, run `bash deploy/deploy.sh`.

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
