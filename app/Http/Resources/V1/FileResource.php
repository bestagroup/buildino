<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'original_name' => $this->original_name,
            'extension' => $this->extension,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'category' => $this->category,
            'is_confidential' => $this->is_confidential,
            'scan_status' => $this->scan_status?->value,
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
            'download_url' => route(
                'api.v1.files.download',
                $this->resource
            ),
        ];
    }
}
