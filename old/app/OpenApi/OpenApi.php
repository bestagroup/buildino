<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Buildino API',
    description: 'Building Management System API'
)]
#[OA\Server(
    url: '/api/v1',
    description: 'Buildino API V1'
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Bearer Token',
    description: 'Laravel Sanctum API Token'
)]
#[OA\Tag(
    name: 'Authentication',
    description: 'Authentication and token management'
)]
#[OA\Tag(
    name: 'Complexes',
    description: 'Complex management'
)]
#[OA\Tag(
    name: 'Buildings',
    description: 'Building management'
)]
#[OA\Tag(
    name: 'Units',
    description: 'Unit management'
)]
#[OA\Tag(
    name: 'Reservations',
    description: 'Facility reservation management'
)]
#[OA\Tag(
    name: 'Payments',
    description: 'Payment management'
)]
final class OpenApi
{
}
