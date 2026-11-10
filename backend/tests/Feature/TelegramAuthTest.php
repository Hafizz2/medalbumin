<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Otp\TelegramGatewayChannel;
use App\Services\OtpService;
use App\Services\Telegram\TelegramLogin;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedContent(false);
        config(['services.telegram.client_id' => '7123456789', 'services.telegram.client_secret' => 'secret']);
    }

    private function identity(array $over = []): array
    {
        return $over + ['sub' => 'tg-sub-1', 'telegram_id' => '555000111', 'name' => 'Hana Tesfaye', 'username' => 'hana_t', 'photo' => null, 'phone' => '251912345678'];
    }

    public function test_app_redeems_ticket_for_token_and_links_existing_phone_account(): void
    {
        $existing = User::query()->forceCreate(['phone' => '+251912345678', 'name' => null]);
        $ticket = app(TelegramLogin::class)->issueTicket($this->identity(), ['intent' => 'app']);

        $res = $this->postJson('/api/v1/auth/telegram', ['ticket' => $ticket, 'device_id' => 'phone-1', 'platform' => 'ios'])->assertOk();
        $this->assertSame($existing->id, $res->json('user.id'));
        $this->assertSame('Hana Tesfaye', $existing->fresh()->name);
        $this->assertSame('555000111', $existing->fresh()->telegram_id);

        // Tickets are single-use.
        $this->postJson('/api/v1/auth/telegram', ['ticket' => $ticket, 'device_id' => 'phone-1'])->assertUnprocessable();
    }

    public function test_id_token_is_verified_against_telegram_keys(): void
    {
        $pem = file_get_contents(base_path('tests/fixtures/test-rsa-key.pem'));
        $details = openssl_pkey_get_details(openssl_pkey_get_private($pem));
        $b64 = fn ($v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
        Http::fake(['oauth.telegram.org/.well-known/jwks.json' => Http::response(['keys' => [[
            'kty' => 'RSA', 'kid' => 'k1', 'alg' => 'RS256', 'use' => 'sig', 'n' => $b64($details['rsa']['n']), 'e' => $b64($details['rsa']['e']),
        ]]])]);

        $claims = ['iss' => 'https://oauth.telegram.org', 'aud' => '7123456789', 'sub' => 'abc', 'id' => 777, 'name' => 'Abel M', 'phone_number' => '251911111111', 'iat' => time(), 'exp' => time() + 600];
        $token = JWT::encode($claims, $pem, 'RS256', 'k1');
        $this->postJson('/api/v1/auth/telegram', ['id_token' => $token, 'device_id' => 'd1'])->assertOk()->assertJsonPath('user.phone', '+251911111111');

        // Wrong audience is rejected.
        Cache::forget('jwks.telegram');
        $bad = JWT::encode(['aud' => 'someone-else'] + $claims, $pem, 'RS256', 'k1');
        $this->postJson('/api/v1/auth/telegram', ['id_token' => $bad, 'device_id' => 'd2'])->assertUnprocessable();
    }

    public function test_redirect_only_returns_tickets_to_the_app(): void
    {
        $res = $this->get('/auth/telegram/redirect?intent=app&return_to='.urlencode('https://evil.example/steal'));
        $res->assertRedirect();
        $this->assertStringStartsWith('https://oauth.telegram.org/auth?', $res->headers->get('Location'));
        parse_str(parse_url($res->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->assertSame('S256', $q['code_challenge_method']);
        $state = Cache::get('tg-login:'.$q['state']);
        $this->assertSame('medalbumin://auth/telegram', $state['context']['return_to']); // evil URL replaced
    }

    public function test_gateway_channel_sends_code_and_maps_errors(): void
    {
        Http::fake([
            'gatewayapi.telegram.org/sendVerificationMessage' => Http::sequence()
                ->push(['ok' => true, 'result' => ['request_id' => 'req-1', 'phone_number' => '+251912345678', 'request_cost' => 0.01]])
                ->push(['ok' => false, 'error' => 'PHONE_NUMBER_INVALID']),
            'gatewayapi.telegram.org/checkVerificationStatus' => Http::response(['ok' => true, 'result' => []]),
        ]);
        $this->app->bind(\App\Services\Otp\OtpChannel::class, fn () => new TelegramGatewayChannel('token-123'));

        $this->postJson('/api/v1/auth/otp/send', ['phone' => '0912345678'])->assertOk()->assertJsonPath('channel', 'telegram')->assertJsonMissing(['debug_code']);
        $this->assertDatabaseHas('otp_codes', ['phone' => '+251912345678', 'provider_request_id' => 'req-1', 'channel' => 'telegram']);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer token-123') && $r['phone_number'] === '+251912345678' && strlen($r['code']) === 6);

        $this->postJson('/api/v1/auth/otp/send', ['phone' => '0922222222'])->assertUnprocessable()->assertJsonValidationErrors('phone');
    }

    public function test_gateway_delivery_report_signature(): void
    {
        config(['services.telegram_gateway.token' => 'tok']);
        \App\Models\OtpCode::query()->create(['phone' => '+251912345678', 'provider_request_id' => 'req-9', 'code_hash' => 'x', 'expires_at' => now()->addMinutes(5)]);
        $body = json_encode(['request_id' => 'req-9', 'delivery_status' => ['status' => 'delivered', 'updated_at' => time()]]);
        $ts = (string) time();
        $sig = hash_hmac('sha256', $ts."\n".$body, hash('sha256', 'tok', true));

        $this->call('POST', '/webhooks/telegram-gateway', [], [], [], ['HTTP_X_REQUEST_TIMESTAMP' => $ts, 'HTTP_X_REQUEST_SIGNATURE' => 'bad', 'CONTENT_TYPE' => 'application/json'], $body)->assertStatus(401);
        $this->call('POST', '/webhooks/telegram-gateway', [], [], [], ['HTTP_X_REQUEST_TIMESTAMP' => $ts, 'HTTP_X_REQUEST_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'], $body)->assertOk();
        $this->assertDatabaseHas('otp_codes', ['provider_request_id' => 'req-9', 'delivery_status' => 'delivered']);
    }

    public function test_website_phone_code_login(): void
    {
        $this->post(route('account.login.code'), ['phone' => '0912345678'])->assertSessionHas('status');
        $code = \App\Models\OtpCode::query()->latest('id')->first();
        // Read the code back from the log channel output (debug mode) via a fresh send is not needed: replace the hash.
        $code->update(['code_hash' => \Illuminate\Support\Facades\Hash::make('123456')]);
        $this->post(route('account.login.verify'), ['code' => '123456'])->assertRedirect(route('account'));
        $this->assertAuthenticated();
        $this->assertSame('+251912345678', auth()->user()->phone);
        $this->assertSame('+251912345678', OtpService::normalizePhone('0912345678'));
    }
}
