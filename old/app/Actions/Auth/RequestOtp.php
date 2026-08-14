<?php
namespace App\Actions\Auth;

use App\Services\Auth\OtpService;

class RequestOtp
{
    public function __construct(private readonly OtpService $service) {}

    public function execute(string $identifier, string $channel, string $purpose, ?string $ip = null): void
    {
        $this->service->request($identifier, $channel, $purpose, $ip);
    }
}
