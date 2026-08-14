<?php

namespace App\Services\Reports\Export;

final class ReportTabularizer
{
    public function rows(
        array $data,
        int $maxRows = 50000
    ): array {
        $rows = [];

        $this->flatten(
            $data,
            '',
            $rows,
            $maxRows + 1
        );

        if (count($rows) > $maxRows) {
            throw new \RuntimeException(
                'Report exceeds the configured export row limit.'
            );
        }

        return $rows;
    }

    private function flatten(
        mixed $value,
        string $path,
        array &$rows,
        int $maxRows
    ): void {
        if (count($rows) >= $maxRows) {
            return;
        }

        if (! is_array($value)) {
            $rows[] = [
                'field' => $path ?: 'value',
                'value' => $this->scalar($value),
            ];

            return;
        }

        if ($value === []) {
            $rows[] = [
                'field' => $path ?: 'value',
                'value' => '',
            ];

            return;
        }

        foreach ($value as $key => $item) {
            if (count($rows) >= $maxRows) {
                break;
            }

            $next = $path === ''
                ? (string) $key
                : $path.'.'.$key;

            $this->flatten(
                $item,
                $next,
                $rows,
                $maxRows
            );
        }
    }

    private function scalar(
        mixed $value
    ): string {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        ) ?: '';
    }
}
