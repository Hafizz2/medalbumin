# MedAlbumin mobile app

React Native app for Android and iOS, built with Expo SDK 58 and Expo Router. All data comes from the Laravel API in `../backend`.

## Run it on your phone (Expo Go)

1. Start the backend so phones on the Wi-Fi can reach it:
   ```
   cd ../backend
   php artisan serve --host=0.0.0.0 --port=8000
   ```
   On Windows, allow port 8000 through the firewall the first time, from an admin PowerShell:
   ```
   New-NetFirewallRule -DisplayName "MedAlbumin API 8000" -Direction Inbound -Protocol TCP -LocalPort 8000 -Action Allow
   ```
2. Set `mobile/.env` to your PC's LAN IP, and set the same address as `APP_URL` in `backend/.env`:
   ```
   EXPO_PUBLIC_API_URL=http://192.168.168.87:8000
   ```
3. Start the app:
   ```
   npm install
   npx expo start
   ```
4. Scan the QR code. On iPhone, use the Camera app; on Android, use the Expo Go app. Your phone and PC must be on the same Wi-Fi. If the network blocks device-to-device traffic, run `npx expo start --tunnel`.

### Signing in while testing
- With `OTP_DRIVER=log` in the backend, no Telegram message is sent. The code is shown in a banner on the sign-in screen and written to `backend/storage/logs/laravel.log`.
- "Continue as guest" works without any keys.
- "Continue with Telegram" needs `TELEGRAM_CLIENT_ID` and `TELEGRAM_CLIENT_SECRET`, plus the redirect URL registered with Telegram. See the backend README.

### Expo Go limits
- **Push notifications:** Android Expo Go can't receive remote push. Use a development build (below) to test push on Android.
- **Apple sign-in:** in Expo Go it signs in as the Expo Go app. Test it properly in a development or TestFlight build.

## Useful commands
| Command | What it does |
|---|---|
| `npm run typecheck` | TypeScript check |
| `npm run i18n` | Rebuilds `src/i18n/en.json` from every `t('key', 'English')` call |
| `npx expo-doctor` | Checks the project for dependency and config problems |

### Translating the app
Every string in the app is written as `t('key', 'English text')`. After adding new text:

```
npm run i18n
cd ../backend && php artisan medalbumin:ui-import en ../mobile/src/i18n/en.json --prune
```

New keys then appear under **Admin → Languages → Strings**, where Amharic, Afaan Oromoo and other languages are translated. The app downloads the live language when it starts, so translators don't need a new app release.

## Store builds (EAS)

```
npm install -g eas-cli
eas login
eas init
eas build:configure
eas build --profile production --platform all
eas submit --platform ios
eas submit --platform android
```

- `eas init` writes `extra.eas.projectId`, which push notifications need.
- `eas build:configure` creates `eas.json`. In each build profile, set `EXPO_PUBLIC_API_URL=https://your-domain` under `env`.

### Before you submit
- **Bundle ID:** `com.medalbumin.app` on both stores. Change it in `app.config.ts` before the first upload if you want a different one.
- **Sign in with Apple:** this is enabled. Apple requires it because the app offers other third-party logins.
- **Payments:** subscriptions are sold on the website only. The app shows plan status with no purchase buttons or links, which keeps it within App Store guideline 3.1.
- **Account deletion:** users can delete their account in the app (Profile → Delete account). There is also a web page at `/delete-account` for Google Play's data-safety form.
- **Privacy:** the privacy policy and terms are at `/privacy` and `/terms` on the backend. The iOS privacy manifest is set in `app.config.ts`.
- **App review login:** give the reviewers the review phone number and code configured in the backend (`OTP_REVIEW_PHONE` and `OTP_REVIEW_CODE` in `backend/.env`).

## Project layout
```
src/app/         screens (Expo Router — the file name is the route)
src/api/         API client, types, React Query hooks
src/components/  design-system pieces (Card, Chip, Button, Screen…)
src/theme/       light and dark colours, fonts, spacing
src/i18n/        i18next setup and the English source strings
src/store/       auth token (SecureStore) and settings (zustand)
```
