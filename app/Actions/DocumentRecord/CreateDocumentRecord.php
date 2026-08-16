<?php

namespace App\Actions\DocumentRecord;

use App\Models\DocumentRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateDocumentRecord
{
    public function execute(
        array $data,
        User $actor
    ): DocumentRecord
    {
        return DB::transaction(
            fn (): DocumentRecord =>
                DocumentRecord::query()->create([
                    ...$data,
                    'created_by' => $actor->getKey(),
                ])
        );
    }
}
