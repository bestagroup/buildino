<?php

namespace Tests\Support;

use App\Models\Block;
use App\Models\Building;
use App\Models\Complex;
use App\Models\Floor;
use App\Models\Unit;
use App\Models\User;

trait CreatesBuildingDomainData
{
    protected function createUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'is_active' => true,
            'is_blocked' => false,
        ], $attributes));
    }

    protected function createBuildingGraph(): array
    {
        $complex = Complex::query()->create([
            'code' => 'C-'.uniqid(),
            'title' => 'Test Complex',
            'province' => 'Tehran',
            'city' => 'Tehran',
            'is_active' => true,
        ]);

        $building = Building::query()->create([
            'complex_id' => $complex->id,
            'code' => 'B-'.uniqid(),
            'title' => 'Test Building',
            'timezone' => 'Asia/Tehran',
            'currency' => 'IRR',
            'is_active' => true,
        ]);

        $block = Block::query()->create([
            'building_id' => $building->id,
            'title' => 'A',
            'is_active' => true,
        ]);

        $floor = Floor::query()->create([
            'block_id' => $block->id,
            'floor_number' => 1,
            'title' => 'First',
        ]);

        $unit = Unit::query()->create([
            'floor_id' => $floor->id,
            'unit_number' => (string) random_int(100, 999),
            'area' => 100,
            'usage_type' => 'residential',
            'is_active' => true,
        ]);

        return compact('complex', 'building', 'block', 'floor', 'unit');
    }
}
