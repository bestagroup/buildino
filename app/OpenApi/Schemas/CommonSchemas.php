<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ValidationErrorResponse',
    required: ['success', 'message', 'code', 'errors'],
    properties: [
        new OA\Property(
            property: 'success',
            type: 'boolean',
            example: false
        ),
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'اطلاعات ارسال‌شده معتبر نیست.'
        ),
        new OA\Property(
            property: 'code',
            type: 'string',
            example: 'VALIDATION_ERROR'
        ),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(
                type: 'array',
                items: new OA\Items(type: 'string')
            )
        ),
    ]
)]
#[OA\Schema(
    schema: 'UnauthenticatedResponse',
    properties: [
        new OA\Property(
            property: 'success',
            type: 'boolean',
            example: false
        ),
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'احراز هویت انجام نشده است.'
        ),
        new OA\Property(
            property: 'code',
            type: 'string',
            example: 'UNAUTHENTICATED'
        ),
    ]
)]
#[OA\Schema(
    schema: 'ForbiddenResponse',
    properties: [
        new OA\Property(
            property: 'success',
            type: 'boolean',
            example: false
        ),
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'شما مجوز انجام این عملیات را ندارید.'
        ),
        new OA\Property(
            property: 'code',
            type: 'string',
            example: 'FORBIDDEN'
        ),
    ]
)]
#[OA\Schema(
    schema: 'NotFoundResponse',
    properties: [
        new OA\Property(
            property: 'success',
            type: 'boolean',
            example: false
        ),
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'منبع موردنظر یافت نشد.'
        ),
        new OA\Property(
            property: 'code',
            type: 'string',
            example: 'RESOURCE_NOT_FOUND'
        ),
    ]
)]
final class CommonSchemas
{
}
