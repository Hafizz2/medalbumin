# MedAlbumin backend

Laravel 12 app that serves:

| Part | URL | Tech |
|---|---|---|
| Public website (SEO landing, privacy, terms, account deletion) | `/` | Blade + Tailwind 4 |
| Student account — sign in with Telegram, buy / extend plans | `/account` | Blade |
| Admin console | `/admin` | Livewire 3 + Alpine + Tiptap; Fortify login (2-step login optional) |
| Mobile API (v1) | `/api/v1/*` | Sanctum bearer tokens, one token per device |
| Interactive API docs | `/docs/api` | Scramble (OpenAPI) |

**Payments happen on the website only.** The apps show plan status but contain no purchase flow,
which keeps them within App Store and Google Play rules for digital subscriptions.

## Run locally (XAMPP)

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate   # then set DB_* (MySQL database "mednexus")
php artisan migrate --seed                          # reference data + first super admin (password printed once)
php artisan db:seed --class=DemoContentSeeder       # optional starter questions, notes and flashcards
php artisan storage:link
npm run build
php artisan serve --host=0.0.0.0 --port=8000        # 0.0.0.0 so phones on the same Wi-Fi can reach it
php artisan queue:work                               # pushes, video compression
php artisan schedule:work                            # reminders, grand-mock status, nightly question quality
```

Tests run on in-memory SQLite: `php artisan test`.

## Sign-in (no SMS)

1. **Phone + code in Telegram** — Telegram Gateway sends the 6-digit code as a Telegram message.
   Set `OTP_DRIVER=telegram` and `TELEGRAM_GATEWAY_TOKEN` (gateway.telegram.org → Account → API).
   With `OTP_DRIVER=log` codes go to `storage/logs/laravel.log` (and are returned as `debug_code` while `APP_DEBUG=true`).
2. **Continue with Telegram** — Telegram's OpenID Connect login. In @BotFather → your bot → *Login Widget*:
   add Allowed URLs `https://YOUR-DOMAIN` and `https://YOUR-DOMAIN/auth/telegram/callback`,
   then copy *Client ID* and *Client Secret* to `TELEGRAM_CLIENT_ID` / `TELEGRAM_CLIENT_SECRET`.
   Needs a public HTTPS address (use a tunnel such as Cloudflare Tunnel while developing).
3. **Sign in with Apple** stays available in the iOS app (Apple requires it when another social login is offered).

## Key settings (.env)

| Key | What it does |
|---|---|
| `OTP_DRIVER` | `telegram` or `log` |
| `TELEGRAM_GATEWAY_TOKEN`, `TELEGRAM_GATEWAY_SENDER` | Telegram Gateway (codes). Delivery reports arrive at `/webhooks/telegram-gateway` when `APP_URL` is https |
| `TELEGRAM_CLIENT_ID`, `TELEGRAM_CLIENT_SECRET`, `TELEGRAM_BOT_USERNAME` | Log In With Telegram |
| `OTP_REVIEW_PHONE`, `OTP_REVIEW_CODE` | Fixed login for App Store / Play reviewers (no message is sent) |
| `CHAPA_SECRET_KEY`, `CHAPA_WEBHOOK_SECRET` | Online payments (Chapa checkout takes Telebirr, CBE Birr, M-Pesa, cards). Webhook: `/webhooks/chapa` |
| `APPLE_CLIENT_IDS` | Allowed audience(s) for Sign in with Apple tokens (the iOS bundle id) |
| `EXPO_ACCESS_TOKEN` | Optional, for Expo push security |
| `FFMPEG_PATH`, `FFPROBE_PATH` | Video compression to 720p/360p |
| `REQUIRE_ADMIN_2FA` | `true` forces every admin to use an authenticator app (default `false`) |
| `PLAY_STORE_URL`, `APP_STORE_URL` | Download buttons on the website |

## Languages

Admin → Languages. English is the default and fallback. A super admin adds a language from the
official list (`config/languages.php`) as a **draft**; editors translate the app text
(Admin → Languages → Translate app) and content (every editor has language tabs); a super admin
then makes it **live**. Students never see draft languages, and anything untranslated shows in English.
App text rules: plain text only, and `{placeholders}` must match English. The app downloads text from
`/api/v1/i18n/{code}` (ETag-cached). The English source keys come from the mobile app's `en.json`,
uploaded on the English page.

## Production checklist

- HTTPS, `APP_ENV=production`, `APP_DEBUG=false`, `OTP_DRIVER=telegram`
- Cron: `* * * * * php artisan schedule:run`; supervisor: `php artisan queue:work --timeout=3600`
- PHP `upload_max_filesize` / `post_max_size` large enough for lesson videos (or use YouTube / link sources)
- Set real plan prices (plans start hidden with price 0) and bank accounts (Admin → Settings → General)
- Medical team reviews lab values, drug doses and all starter content
