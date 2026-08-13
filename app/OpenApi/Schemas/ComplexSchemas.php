<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Complex',
    properties: [
        new OA\Property(
            property: 'id',
            type: 'integer',
            example: 1
        ),

        new OA\Property(
            property: 'code',
            type: 'string',
            example: 'CMP-001'
        ),

        new OA\Property(
            property: 'title',
            type: 'string',
            example: 'مجتمع مسکونی آفتاب'
        ),

        new OA\Property(
            property: 'province',
            type: 'string',
            example: 'تهران'
        ),

        new OA\Property(
            property: 'city',
            type: 'string',
            example: 'تهران'
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
            property: 'sort_order',
            type: 'integer',
            example: 0
        ),

        new OA\Property(
            property: 'is_active',
            type: 'boolean',
            example: true
        ),
    ]
)]
#[OA\Schema(
    schema: 'ComplexRequest',
    required: [
        'code',
        'title',
        'province',
        'city',
    ],
    properties: [
        new OA\Property(
            property: 'code',
            type: 'string',
            example: 'CMP-001'
        ),

        new OA\Property(
            property: 'title',
            type: 'string',
            example: 'مجتمع مسکونی آفتاب'
        ),

        new OA\Property(
            property: 'province',
            type: 'string',
            example: 'تهران'
        ),

        new OA\Property(
            property: 'city',
            type: 'string',
            example: 'تهران'
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
            property: 'sort_order',
            type: 'integer',
            example: 0
        ),

        new OA\Property(
            property: 'is_active',
            type: 'boolean',
            example: true
        ),
    ]
)]
final class ComplexSchemas
{
}
