<?php

namespace Tests\Unit\Notifications;

use App\Contracts\Notifications\SmsSender;
use App\Data\Notifications\NotificationMessage;
use App\Data\Notifications\SmsTemplateMessage;
use App\Models\User;
use App\Services\Auth\NotificationOtpSender;
use App\Services\Notifications\GhasedakSmsSender;
use App\Services\Notifications\LogSmsSender;
use App\Services\Notifications\UserNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SmsTemplateContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['notifications.sms_provider' => 'ghasedak', 'notifications.ghasedak.api_key' => 'test-key']);
        $this->app->instance(SmsSender::class, new GhasedakSmsSender);
        Http::preventStrayRequests();
    }

    public function test_login_sends_only_the_explicit_otp_and_preserves_leading_zeroes(): void
    {
        Http::fake(['*' => Http::response(['result' => ['code' => 200], 'items' => [123]], 200)]);
        app(NotificationOtpSender::class)->send('09121111111', 'sms', '001234');
        Http::assertSent(fn ($request) => $request->data() === [
            'type' => 1, 'receptor' => '09121111111', 'template' => 'buildinoverify', 'param1' => '001234',
        ]);
        Http::assertSentCount(1);
    }

    public function test_invitation_sends_all_three_parameters_without_extracting_numbers(): void
    {
        Http::fake(['*' => Http::response(['result' => ['code' => 200], 'items' => [456]], 200)]);
        $result = app(SmsSender::class)->send('09121111111', new SmsTemplateMessage('unit.invitation', [
            'param1' => '00123456', 'param2' => 'محمد رضایی', 'param3' => 'ساختمان ۱۴۰۵، واحد ۱۰۱',
        ]));
        $this->assertSame(456, $result['provider_message_id']);
        Http::assertSent(fn ($request) => $request->data() === [
            'type' => 1, 'receptor' => '09121111111', 'template' => 'BuildinoInvitation',
            'param1' => '00123456', 'param2' => 'محمد رضایی', 'param3' => 'ساختمان ۱۴۰۵، واحد ۱۰۱',
        ]);
    }

    public static function invalidMessages(): array
    {
        return [
            'raw text' => ['code 123456', null],
            'unknown purpose' => ['invoice.issued', ['param1' => '1234']],
            'missing name and unit' => ['unit.invitation', ['param1' => '12345678']],
            'extra login parameter' => ['auth.otp', ['param1' => '123456', 'param2' => 'name']],
            'empty parameter' => ['auth.otp', ['param1' => '']],
            'unsupported parameter' => ['auth.otp', ['param4' => '123456']],
        ];
    }

    #[DataProvider('invalidMessages')]
    public function test_invalid_messages_never_reach_gateway(string $purpose, ?array $parameters): void
    {
        Http::fake();
        try {
            app(SmsSender::class)->send('09121111111', $parameters === null ? $purpose : new SmsTemplateMessage($purpose, $parameters));
            $this->fail('Invalid message was accepted.');
        } catch (\InvalidArgumentException) {
            Http::assertNothingSent();
        }
    }

    public function test_provider_error_with_http_success_is_not_reported_as_sent(): void
    {
        Http::fake(['*' => Http::response(['result' => ['code' => 400, 'message' => 'private provider detail']], 200)]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Ghasedak rejected');
        app(SmsSender::class)->send('09121111111', new SmsTemplateMessage('auth.otp', ['param1' => '123456']));
    }

    public function test_unconfigured_notification_is_skipped_without_a_sent_log_and_database_channel_still_works(): void
    {
        Http::fake();
        $user = User::factory()->create(['mobile' => '09121111111', 'is_active' => true, 'is_blocked' => false]);
        $message = new NotificationMessage('invoice.issued', 'صورتحساب', 'مبلغ 123456');
        $service = app(UserNotificationService::class);
        $this->assertNull($service->send($user, $message, 'sms', 'unconfigured-sms'));
        $this->assertDatabaseMissing('notification_logs', ['idempotency_key' => 'unconfigured-sms']);
        $this->assertNotNull($service->send($user, $message, 'database', 'unconfigured-database'));
        Http::assertNothingSent();
    }

    public function test_future_notification_requires_configured_template_and_explicit_parameters(): void
    {
        config(['notifications.sms_templates.invoice.issued' => ['template' => 'future-template', 'parameters' => ['param1', 'param2']]]);
        Http::fake(['*' => Http::response(['result' => ['code' => 200], 'items' => [789]], 200)]);
        $user = User::factory()->create(['mobile' => '09121111111', 'is_active' => true, 'is_blocked' => false]);
        $message = new NotificationMessage('invoice.issued', 'صورتحساب', 'متن اعلان', ['sms_parameters' => ['param1' => 'INV-1234', 'param2' => '250000']]);
        app(UserNotificationService::class)->send($user, $message, 'sms', 'future-sms');
        Http::assertSent(fn ($request) => $request['template'] === 'future-template' && $request['param1'] === 'INV-1234' && $request['param2'] === '250000');
    }

    public function test_log_provider_does_not_record_codes_or_personal_parameters(): void
    {
        Log::spy();
        (new LogSmsSender)->send('09121111111', new SmsTemplateMessage('unit.invitation', [
            'param1' => '12345678', 'param2' => 'نام خصوصی', 'param3' => 'ساختمان خصوصی',
        ]));
        Log::shouldHaveReceived('info')->once()->with('SMS notification (log provider)', [
            'recipient' => '*******1111', 'purpose' => 'unit.invitation',
        ]);
    }
}
