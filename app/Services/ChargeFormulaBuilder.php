<?php

namespace App\Services;

final class ChargeFormulaBuilder
{
    public const VERSION = 1;

    public function normalize(array $data): array
    {
        if (! isset($data['builder'])) {
            return $data;
        }

        $builder = $data['builder'];

        $data['calculation_type'] = $builder['calculation_type'];
        $data['configuration'] = [
            'generated_by' => 'guided_builder',
            'builder_version' => self::VERSION,
        ];
        $data['items'] = collect($builder['items'])
            ->map(fn (array $item): array => [
                'financial_category_id' =>
                    $item['financial_category_id'] ?? null,
                'title' => trim($item['title']),
                'base_amount' => (int) $item['base_amount'],
                'configuration' => null,
            ])
            ->values()
            ->all();

        unset($data['builder']);

        return $data;
    }
}
