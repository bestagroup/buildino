<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'OtpRequest',
    required: ['mobile'],
    properties: [
        new OA\Property(
            property: 'mobile',
            type: 'string',
            example: '09121234567'
        ),
    ]
)]
#[OA\Schema(
    schema: 'OtpLoginRequest',
    required: ['mobile', 'code'],
    properties: [
        new OA\Property(
            property: 'mobile',
            type: 'string',
            example: '09121234567'
        ),
        new OA\Property(
            property: 'code',
            type: 'string',
            example: '123456'
        ),
        new OA\Property(
            property: 'device_name',
            type: 'string',
            example: 'android-app'
        ),
    ]
)]
#[OA\Schema(
    schema: 'PasswordLoginRequest',
    required: ['mobile', 'password'],
    properties: [
        new OA\Property(
            property: 'mobile',
            type: 'string',
            example: '09121234567'
        ),
        new OA\Property(
            property: 'password',
            type: 'string',
            format: 'password',
            example: '********'
        ),
        new OA\Property(
            property: 'device_name',
            type: 'string',
            example: 'web'
        ),
    ]
)]
#[OA\Schema(
    schema: 'AuthenticatedUser',
    properties: [
        new OA\Property(
            property: 'id',
            type: 'integer',
            example: 1
        ),
        new OA\Property(
            property: 'first_name',
            type: 'string',
            example: 'حسین'
        ),
        new OA\Property(
            property: 'last_name',
            type: 'string',
            example: 'احمدی'
        ),
        new OA\Property(
            property: 'mobile',
            type: 'string',
            example: '09121234567'
        ),
        new OA\Property(
            property: 'email',
            type: 'string',
            nullable: true,
            example: 'user@example.com'
        ),
    ]
)]
#[OA\Schema(
    schema: 'LoginResponse',
    properties: [
        new OA\Property(
            property: 'success',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'ورود با موفقیت انجام شد.'
        ),
        new OA\Property(
            property: 'data',
            properties: [
                new OA\Property(
                    property: 'token',
                    type: 'string',
                    example: '1|xxxxxxxxxxxxxxxx'
                ),
                new OA\Property(
                    property: 'token_type',
                    type: 'string',
                    example: 'Bearer'
                ),
                new OA\Property(
                    property: 'user',
                    ref: '#/components/schemas/AuthenticatedUser'
                ),
            ],
            type: 'object'
        ),
    ]
)]
final class AuthSchemas
{
}
