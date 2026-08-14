<?php
namespace App\Actions\Auth;

use App\Services\Auth\AuthenticationService;
use App\Services\Auth\OtpService;
use Illuminate\Http\Request;

class LoginWithOtp
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly AuthenticationService $auth,
    ) {}

    public function execute(array $data, Request $request): array
    {
        $this->otp->verify($data['identifier'], $data['channel'], 'login', $data['code']);
        return $this->auth->loginWithVerifiedOtp($data['identifier'], $data['channel'], $data['device_name'], $request);
    }
}
