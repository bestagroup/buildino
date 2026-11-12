<?php

namespace Tests\Unit\Auth;

use App\Contracts\Notifications\SmsSender;
use App\Data\Notifications\SmsTemplateMessage;
use App\Services\Auth\NotificationOtpSender;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class LocalOtpLoggingTest extends TestCase
{
    #[DataProvider('environmentsAndChannels')]
    public function test_delivered_otp_is_logged_only_locally(string $environment, string $channel): void
    {
        $this->app->instance('env', $environment);
        config()->set('auth_otp.log_channel', 'single');
        $identifier = $channel === 'sms' ? '09121111111' : 'local@example.test';
        $sms = Mockery::mock(SmsSender::class);

        if ($channel === 'sms') {
            $sms->shouldReceive('send')->once()->with($identifier, Mockery::on(
                fn ($message) => $message instanceof SmsTemplateMessage
                    && $message->purpose === 'auth.otp'
                    && $message->parameters === ['param1' => '001234']
            ))->andReturn(['accepted' => true]);
        } else {
            $sms->shouldNotReceive('send');
            Mail::shouldReceive('raw')->once()->with('کد تأیید Buildino: 001234', Mockery::type('callable'));
        }

        if ($environment === 'local') {
            Log::shouldReceive('channel')->once()->with('single')->andReturnSelf();
            Log::shouldReceive('info')->once()->with('OTP generated', [
                'identifier' => $identifier, 'channel' => $channel, 'code' => '001234',
            ]);
        } else {
            Log::shouldReceive('channel')->never();
            Log::shouldReceive('info')->never();
        }

        app()->instance(SmsSender::class, $sms);
        app(NotificationOtpSender::class)->send($identifier, $channel, '001234');
    }

    public static function environmentsAndChannels(): array
    {
        $cases = [];
        foreach (['local', 'production', 'staging', 'testing'] as $environment) {
            foreach (['sms', 'email'] as $channel) {
                $cases[$environment.' '.$channel] = [$environment, $channel];
            }
        }

        return $cases;
    }
}
