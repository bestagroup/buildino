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
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Authentication',
    description: 'Authentication and access token management'
)]
class AuthController extends Controller
{
    #[OA\Post(
        path: '/auth/otp/request',
        operationId: 'authRequestOtp',
        summary: 'Request OTP',
        description: 'Requests a one-time verification code for authentication.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['identifier', 'channel'],
                properties: [
                    new OA\Property(
                        property: 'identifier',
                        type: 'string',
                        example: '09121234567'
                    ),
                    new OA\Property(
                        property: 'channel',
                        type: 'string',
                        example: 'sms'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 202,
                description: 'OTP request accepted',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'If the account is eligible, a verification code has been sent.'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ValidationErrorResponse'
                )
            ),
            new OA\Response(
                response: 429,
                description: 'Too many requests'
            ),
        ]
    )]
    public function requestOtp(
        RequestOtpRequest $request,
        RequestOtp $action
    ): JsonResponse {
        $data = $request->validated();

        $action->execute(
            $data['identifier'],
            $data['channel'],
            'login',
            $request->ip()
        );

        return response()->json([
            'message' => 'If the account is eligible, a verification code has been sent.',
        ], 202);
    }

    #[OA\Post(
        path: '/auth/otp/login',
        operationId: 'authLoginWithOtp',
        summary: 'Login with OTP',
        description: 'Authenticates a user using a previously issued OTP.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: '#/components/schemas/OtpLoginRequest'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authentication successful',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/LoginResponse'
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ValidationErrorResponse'
                )
            ),
            new OA\Response(
                response: 429,
                description: 'Too many requests'
            ),
        ]
    )]
    public function loginWithOtp(
        VerifyOtpRequest $request,
        LoginWithOtp $action
    ): JsonResponse {
        $result = $action->execute(
            $request->validated(),
            $request
        );

        return response()->json([
            'data' => new AuthUserResource($result['user']),
            'access_token' => $result['token'],
            'token_type' => $result['token_type'],
        ]);
    }

    #[OA\Post(
        path: '/auth/password/login',
        operationId: 'authLoginWithPassword',
        summary: 'Login with password',
        description: 'Authenticates a user using credentials and returns a Sanctum access token.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: '#/components/schemas/PasswordLoginRequest'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authentication successful',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/LoginResponse'
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Invalid credentials',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/UnauthenticatedResponse'
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ValidationErrorResponse'
                )
            ),
            new OA\Response(
                response: 429,
                description: 'Too many requests'
            ),
        ]
    )]
    public function loginWithPassword(
        PasswordLoginRequest $request,
        LoginWithPassword $action
    ): JsonResponse {
        $result = $action->execute(
            $request->validated(),
            $request
        );

        return response()->json([
            'data' => new AuthUserResource($result['user']),
            'access_token' => $result['token'],
            'token_type' => $result['token_type'],
        ]);
    }

    #[OA\Get(
        path: '/auth/me',
        operationId: 'authMe',
        summary: 'Get authenticated user',
        description: 'Returns the currently authenticated user.',
        security: [
            ['sanctum' => []],
        ],
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authenticated user',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/AuthenticatedUser'
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/UnauthenticatedResponse'
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ForbiddenResponse'
                )
            ),
        ]
    )]
    public function me(Request $request): AuthUserResource
    {
        return new AuthUserResource(
            $request->user()
        );
    }

    #[OA\Post(
        path: '/auth/logout',
        operationId: 'authLogout',
        summary: 'Logout current device',
        description: 'Revokes the current Sanctum access token.',
        security: [
            ['sanctum' => []],
        ],
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Logged out successfully'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/UnauthenticatedResponse'
                )
            ),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()
            ->currentAccessToken()
            ?->delete();

        return response()->json(status: 204);
    }

    #[OA\Post(
        path: '/auth/logout-all',
        operationId: 'authLogoutAll',
        summary: 'Logout all devices',
        description: 'Revokes all Sanctum access tokens belonging to the authenticated user.',
        security: [
            ['sanctum' => []],
        ],
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 204,
                description: 'All access tokens revoked'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/UnauthenticatedResponse'
                )
            ),
        ]
    )]
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()
            ->tokens()
            ->delete();

        return response()->json(status: 204);
    }
}
