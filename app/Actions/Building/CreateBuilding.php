<?php

namespace App\Actions\Building;

use App\Models\Building;
use App\Models\Complex;
use Illuminate\Support\Facades\DB;

class CreateBuilding
{
    public function execute(array $data): Building
    {
        return DB::transaction(function () use ($data): Building {
            $parent = Complex::query()->lockForUpdate()->findOrFail($data['complex_id']);
            abort_if(in_array($parent->workspace_type, ['standalone', 'unlinked'], true) && $parent->buildings()->exists(), 422, 'این مجموعه فقط یک ساختمان یا بلوک دارد.');
            $data['structure_type'] = match ($parent->workspace_type) {
                'standalone' => 'standalone',
                'complex', 'unlinked' => 'complex_block',
                default => $data['structure_type'] ?? null,
            };
            return Building::query()->create($data);
        });
    }
}
