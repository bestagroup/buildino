<?php
namespace App\Actions\Auth;

use App\Services\Auth\AuthenticationService;
use Illuminate\Http\Request;

class LoginWithPassword
{
    public function __construct(private readonly AuthenticationService $service) {}

    public function execute(array $data, Request $request): array
    {
        return $this->service->loginWithPassword($data['login'], $data['password'], $data['device_name'], $request);
    }
}
