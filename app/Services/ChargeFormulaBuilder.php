<?php

namespace App\Services;

use App\Enums\ChargeCalculationType;

final class ChargeFormulaBuilder
{
    public const VERSION = 2;

    public function normalize(array $data): array
    {
        if (! isset($data['builder'])) {
            return $data;
        }

        $builder = $data['builder'];
        $type = ChargeCalculationType::from(
            (string) $builder['calculation_type']
        );

        $configuration = [
            'generated_by' => 'guided_builder',
            'builder_version' => self::VERSION,
        ];

        if (
            $type
            === ChargeCalculationType::FloorCoefficient
        ) {
            $configuration['floor_coefficients'] =
                collect(
                    $builder['floor_coefficients'] ?? []
                )
                    ->mapWithKeys(
                        fn ($value, $floor): array => [
                            (string) $floor =>
                                (float) $value,
                        ]
                    )
                    ->all();
        }

        $data['calculation_type'] = $type->value;
        $data['configuration'] = $configuration;
        $data['items'] = [[
            'financial_category_id' =>
                (int) $builder['financial_category_id'],
            'title' => trim((string) $data['title']),
            'base_amount' =>
                $type === ChargeCalculationType::Fixed
                    ? (int) (
                        $builder['fixed_amount']
                        ?? 0
                    )
                    : 0,
            'configuration' => null,
        ]];

        unset($data['builder']);

        return $data;
    }
}
