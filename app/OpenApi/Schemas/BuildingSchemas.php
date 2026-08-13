<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'BuildingRequest',
    required: [
        'complex_id',
        'code',
        'title',
    ],
    properties: [
        new OA\Property(
            property: 'complex_id',
            type: 'integer',
            example: 1
        ),

        new OA\Property(
            property: 'code',
            type: 'string',
            example: 'BLD-001'
        ),

        new OA\Property(
            property: 'title',
            type: 'string',
            example: 'ساختمان شماره یک'
        ),

        new OA\Property(
            property: 'building_number',
            type: 'string',
            nullable: true,
            example: 'A'
        ),

        new OA\Property(
            property: 'address',
            type: 'string',
            nullable: true
        ),

        new OA\Property(
            property: 'postal_code',
            type: 'string',
            nullable: true
        ),

        new OA\Property(
            property: 'latitude',
            type: 'number',
            format: 'double',
            nullable: true
        ),

        new OA\Property(
            property: 'longitude',
            type: 'number',
            format: 'double',
            nullable: true
        ),

        new OA\Property(
            property: 'timezone',
            type: 'string',
            example: 'Asia/Tehran'
        ),

        new OA\Property(
            property: 'currency',
            type: 'string',
            example: 'IRR'
        ),

        new OA\Property(
            property: 'floors_count',
            type: 'integer',
            minimum: 0,
            example: 10
        ),

        new OA\Property(
            property: 'units_count',
            type: 'integer',
            minimum: 0,
            example: 40
        ),

        new OA\Property(
            property: 'parking_count',
            type: 'integer',
            minimum: 0,
            example: 40
        ),

        new OA\Property(
            property: 'storage_count',
            type: 'integer',
            minimum: 0,
            example: 40
        ),

        new OA\Property(
            property: 'construction_year',
            type: 'integer',
            nullable: true,
            example: 1402
        ),

        new OA\Property(
            property: 'is_active',
            type: 'boolean',
            example: true
        ),
    ]
)]
#[OA\Schema(
    schema: 'Building',
    properties: [
        new OA\Property(
            property: 'id',
            type: 'integer',
            example: 1
        ),

        new OA\Property(
            property: 'complex_id',
            type: 'integer',
            example: 1
        ),

        new OA\Property(
            property: 'code',
            type: 'string',
            example: 'BLD-001'
        ),

        new OA\Property(
            property: 'title',
            type: 'string',
            example: 'ساختمان شماره یک'
        ),

        new OA\Property(
            property: 'building_number',
            type: 'string',
            nullable: true
        ),

        new OA\Property(
            property: 'timezone',
            type: 'string',
            example: 'Asia/Tehran'
        ),

        new OA\Property(
            property: 'currency',
            type: 'string',
            example: 'IRR'
        ),

        new OA\Property(
            property: 'construction_year',
            type: 'integer',
            nullable: true
        ),

        new OA\Property(
            property: 'is_active',
            type: 'boolean'
        ),
    ]
)]
final class BuildingSchemas
{
}
