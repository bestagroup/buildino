<?php

namespace App\Actions\DocumentRecord;

use App\Models\DocumentRecord;
use Illuminate\Support\Facades\DB;

class CreateDocumentRecord
{
    public function execute(array $data): DocumentRecord
    {
        return DB::transaction(fn (): DocumentRecord => DocumentRecord::query()->create($data));
    }
}
