<?php

namespace App\Services\System;

final class IranLocations
{
    private ?array $provinces = null;

    private ?array $cities = null;

    public function provinces(): array
    {
        return $this->provinces ??= array_column($this->read('provinces.json'), null, 'id');
    }

    public function cities(): array
    {
        return $this->cities ??= array_column($this->read('cities-filtered.json'), null, 'id');
    }

    public function province(mixed $id): ?array
    {
        return is_scalar($id) ? ($this->provinces()[(string) $id] ?? null) : null;
    }

    public function city(mixed $id): ?array
    {
        return is_scalar($id) ? ($this->cities()[(string) $id] ?? null) : null;
    }

    public function provinceByName(mixed $name): ?array
    {
        if (! is_string($name)) {
            return null;
        }

        foreach ($this->provinces() as $province) {
            if ($this->nameKey($province['name']) === $this->nameKey($name)) {
                return $province;
            }
        }

        return null;
    }

    public function cityByName(mixed $provinceName, mixed $cityName): ?array
    {
        $province = $this->provinceByName($provinceName);
        if (! $province || ! is_string($cityName)) {
            return null;
        }

        foreach ($this->cities() as $city) {
            if ($city['province_id'] === $province['id'] && $this->nameKey($city['name']) === $this->nameKey($cityName)) {
                return $city;
            }
        }

        return null;
    }

    /** IDs are lookup identifiers; existing address columns still store names. */
    public function forForm(): array
    {
        $counties = array_column($this->read('counties.json'), 'name', 'id');
        $names = [];
        foreach ($this->cities() as $city) {
            $key = $city['province_id'].':'.$this->nameKey($city['name']);
            $names[$key] = ($names[$key] ?? 0) + 1;
        }

        $provinces = [];
        foreach ($this->provinces() as $province) {
            $provinces[$province['id']] = ['id' => (string) $province['id'], 'name' => $province['name'], 'cities' => []];
        }
        foreach ($this->cities() as $city) {
            $key = $city['province_id'].':'.$this->nameKey($city['name']);
            $provinces[$city['province_id']]['cities'][] = [
                'id' => (string) $city['id'],
                'name' => $city['name'],
                'label' => $names[$key] > 1 ? $city['name'].' — شهرستان '.$counties[$city['county_id']] : $city['name'],
            ];
        }

        $sort = fn (array $a, array $b): int => strcmp($this->sortKey($a['name']), $this->sortKey($b['name']));
        uasort($provinces, $sort);
        foreach ($provinces as &$province) {
            usort($province['cities'], $sort);
        }

        return array_values($provinces);
    }

    private function nameKey(string $name): string
    {
        return preg_replace('/[\s\x{200C}\x{200D}\x{064B}-\x{065F}]+/u', '', strtr(trim($name), ['ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک'])) ?? $name;
    }

    private function sortKey(string $name): string
    {
        // Keep Persian alphabet ordering even on PHP installations without intl.
        static $order = null;
        if ($order === null) {
            $order = [];
            foreach (mb_str_split('آابپتثجچحخدذرزژسشصضطظعغفقکگلمنوهی') as $position => $letter) {
                $order[$letter] = sprintf('%02d', $position);
            }
        }

        return strtr($this->nameKey($name), $order);
    }

    private function read(string $filename): array
    {
        return json_decode(file_get_contents(resource_path('data/iran/'.$filename)), true, flags: JSON_THROW_ON_ERROR);
    }
}
