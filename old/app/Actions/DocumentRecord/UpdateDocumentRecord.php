<?php

namespace App\Actions\DocumentRecord;

use App\Models\DocumentRecord;
use Illuminate\Support\Facades\DB;

class UpdateDocumentRecord
{
    public function execute(DocumentRecord $model, array $data): DocumentRecord
    {
        return DB::transaction(function () use ($model, $data): DocumentRecord {
            $model->update($data);
            return $model->refresh();
        });
    }
}
