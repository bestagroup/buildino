<?php

namespace Tests\Feature\Auth;

use App\Contracts\Auth\OtpSender;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OtpSecurityTest extends TestCase
{
    use RefreshDatabase;

    private object $sender;

    private OtpService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sender = new class implements OtpSender
        {
            /** @var array<string, string> */
            public array $codes = [];

            public function send(string $identifier, string $channel, string $code): void
            {
                $this->codes[$identifier] = $code;
            }
        };

        $this->app->instance(OtpSender::class, $this->sender);
        $this->service = $this->app->make(OtpService::class);
    }

    public function test_requested_otp_is_stored_only_as_a_secure_hash(): void
    {
        $user = $this->user('09121000001');
        $code = $this->requestCode($user);
        $otp = OtpCode::query()->where('identifier', $user->mobile)->sole();

        $this->assertFalse(Schema::hasColumn('otp_codes', 'code'));
        $this->assertNotSame($code, $otp->getRawOriginal('code_hash'));
        $this->assertTrue(Hash::check($code, $otp->getRawOriginal('code_hash')));
    }

    public function test_forward_migration_invalidates_plaintext_rows_and_restores_hash_only_schema(): void
    {
        Schema::table('otp_codes', function (Blueprint $table): void {
            $table->dropColumn('code_hash');
        });
        Schema::table('otp_codes', function (Blueprint $table): void {
            $table->string('code', 8);
        });

        DB::table('otp_codes')->insert([
            'identifier' => '09121000999',
            'channel' => 'sms',
            'purpose' => 'login',
            'code' => '123456',
            'expires_at' => now()->addMinute(),
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path(
            'migrations/2026_08_21_000001_restore_hashed_otp_codes.php'
        );
        $migration->up();

        $this->assertDatabaseCount('otp_codes', 0);
        $this->assertFalse(Schema::hasColumn('otp_codes', 'code'));
        $this->assertTrue(Schema::hasColumn('otp_codes', 'code_hash'));

        $hash = Hash::make('654321');
        OtpCode::query()->create([
            'identifier' => '09121000999',
            'channel' => 'sms',
            'purpose' => 'login',
            'code_hash' => $hash,
            'expires_at' => now()->addMinute(),
            'attempts' => 0,
        ]);

        $storedHash = OtpCode::query()
            ->where('identifier', '09121000999')
            ->value('code_hash');

        $this->assertSame($hash, $storedHash);
        $this->assertTrue(Hash::check('654321', $storedHash));
    }

    public function test_correct_otp_verifies_and_consumed_otp_cannot_be_reused(): void
    {
        $user = $this->user('09121000002');
        $code = $this->requestCode($user);

        $verified = $this->service->verify($user->mobile, 'sms', 'login', $code);

        $this->assertNotNull($verified->verified_at);
        $this->assertNotNull($verified->consumed_at);

        $this->expectException(ValidationException::class);
        $this->service->verify($user->mobile, 'sms', 'login', $code);
    }

    public function test_requesting_new_otp_consumes_previous_outstanding_otp(): void
    {
        $user = $this->user('09121000007');
        $this->requestCode($user);
        $first = OtpCode::query()->where('identifier', $user->mobile)->sole();

        $this->travel((int) config('auth_otp.resend_after', 60) + 1)->seconds();
        $this->requestCode($user);

        $records = OtpCode::query()
            ->where('identifier', $user->mobile)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $records);
        $this->assertNotNull($records->first()->consumed_at);
        $this->assertNull($records->last()->consumed_at);
        $this->assertSame($first->id, $records->first()->id);
    }

    public function test_incorrect_otp_persists_failed_attempt(): void
    {
        $user = $this->user('09121000003');
        $code = $this->requestCode($user);

        try {
            $this->service->verify($user->mobile, 'sms', 'login', $this->wrongCode($code));
            $this->fail('Incorrect OTP was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['OTP is invalid or expired.'],
                $exception->errors()['code']
            );
        }

        $this->assertDatabaseHas('otp_codes', [
            'identifier' => $user->mobile,
            'attempts' => 1,
            'consumed_at' => null,
        ]);
    }

    public function test_attempt_limit_is_enforced_without_rolling_back_attempts(): void
    {
        config()->set('auth_otp.max_attempts', 2);
        $user = $this->user('09121000004');
        $code = $this->requestCode($user);
        $wrong = $this->wrongCode($code);

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $this->service->verify($user->mobile, 'sms', 'login', $wrong);
                $this->fail('Incorrect OTP was accepted.');
            } catch (ValidationException $exception) {
                $this->assertSame(
                    ['OTP is invalid or expired.'],
                    $exception->errors()['code']
                );
                $this->assertSame(
                    $attempt,
                    OtpCode::query()->where('identifier', $user->mobile)->value('attempts')
                );
            }
        }

        try {
            $this->service->verify($user->mobile, 'sms', 'login', $code);
            $this->fail('Attempt-limited OTP was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Maximum OTP attempts exceeded.'],
                $exception->errors()['code']
            );
        }

        $this->assertSame(
            2,
            OtpCode::query()->where('identifier', $user->mobile)->value('attempts')
        );
    }

    public function test_expired_otp_is_rejected(): void
    {
        $user = $this->user('09121000005');
        $code = $this->requestCode($user);
        OtpCode::query()
            ->where('identifier', $user->mobile)
            ->update(['expires_at' => now()->subSecond()]);

        try {
            $this->service->verify($user->mobile, 'sms', 'login', $code);
            $this->fail('Expired OTP was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['OTP is invalid or expired.'],
                $exception->errors()['code']
            );
        }
    }

    public function test_invalid_otp_api_response_uses_authoritative_auth_error_code(): void
    {
        $user = $this->user('09121000006');
        $code = $this->requestCode($user);

        $this->postJson('/api/v1/auth/otp/login', [
            'identifier' => $user->mobile,
            'channel' => 'sms',
            'code' => $this->wrongCode($code),
            'device_name' => 'otp-security-test',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'AUTH_INVALID_CREDENTIALS');
    }

    private function user(string $mobile): User
    {
        return User::factory()->create([
            'mobile' => $mobile,
            'email' => null,
        ]);
    }

    private function requestCode(User $user): string
    {
        $this->service->request($user->mobile, 'sms', 'login', '127.0.0.1');

        $this->assertArrayHasKey($user->mobile, $this->sender->codes);

        return $this->sender->codes[$user->mobile];
    }

    private function wrongCode(string $code): string
    {
        return $code === '000000' ? '111111' : '000000';
    }
}
