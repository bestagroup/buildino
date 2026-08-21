<?php
namespace App\Actions\Auth;

use App\Exceptions\ApiException;
use App\Services\Auth\AuthenticationService;
use App\Services\Auth\OtpService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LoginWithOtp
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly AuthenticationService $auth,
    ) {}

    public function execute(array $data, Request $request): array
    {
        try {
            $this->otp->verify($data['identifier'], $data['channel'], 'login', $data['code']);
        } catch (ValidationException) {
            throw new ApiException(
                'AUTH_INVALID_CREDENTIALS',
                'The provided credentials are invalid.',
                401
            );
        }

        return $this->auth->loginWithVerifiedOtp($data['identifier'], $data['channel'], $data['device_name'], $request);
    }
}
