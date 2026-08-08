<?php
namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\LoginWithOtp;
use App\Actions\Auth\LoginWithPassword;
use App\Actions\Auth\RequestOtp;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordLoginRequest;
use App\Http\Requests\Auth\RequestOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\V1\AuthUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function requestOtp(RequestOtpRequest $request, RequestOtp $action): JsonResponse
    {
        $data = $request->validated();
        $action->execute($data['identifier'], $data['channel'], 'login', $request->ip());

        return response()->json(['message' => 'If the account is eligible, a verification code has been sent.'], 202);
    }

    public function loginWithOtp(VerifyOtpRequest $request, LoginWithOtp $action): JsonResponse
    {
        $result = $action->execute($request->validated(), $request);

        return response()->json([
            'data' => new AuthUserResource($result['user']),
            'access_token' => $result['token'],
            'token_type' => $result['token_type'],
        ]);
    }

    public function loginWithPassword(PasswordLoginRequest $request, LoginWithPassword $action): JsonResponse
    {
        $result = $action->execute($request->validated(), $request);

        return response()->json([
            'data' => new AuthUserResource($result['user']),
            'access_token' => $result['token'],
            'token_type' => $result['token_type'],
        ]);
    }

    public function me(Request $request): AuthUserResource
    {
        return new AuthUserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(status: 204);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();
        return response()->json(status: 204);
    }
}
