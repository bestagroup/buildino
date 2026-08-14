<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeneratedReportResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,

            'report_definition_id' =>
                $this->report_definition_id,

            'report_definition' =>
                $this->whenLoaded(
                    'reportDefinition',
                    fn (): array => [
                        'id' =>
                            $this
                                ->reportDefinition
                                ->id,

                        'code' =>
                            $this
                                ->reportDefinition
                                ->code,

                        'title' =>
                            $this
                                ->reportDefinition
                                ->title,
                    ]
                ),

            'building_id' =>
                $this->building_id,

            'generated_by' =>
                $this->generated_by,

            'format' =>
                is_object($this->format)
                    ? $this->format->value
                    : $this->format,

            'status' =>
                is_object($this->status)
                    ? $this->status->value
                    : $this->status,

            'filters' =>
                $this->filters,

            'started_at' =>
                $this
                    ->started_at
                    ?->toISOString(),

            'completed_at' =>
                $this
                    ->completed_at
                    ?->toISOString(),

            'failed_at' =>
                $this
                    ->failed_at
                    ?->toISOString(),

            'error_message' =>
                $this->error_message,

            'file' =>
                $this->when(
                    $this->relationLoaded('file')
                    && $this->file,
                    fn (): array => [
                        'id' =>
                            $this->file->id,

                        'name' =>
                            $this
                                ->file
                                ->original_name,

                        'mime_type' =>
                            $this
                                ->file
                                ->mime_type,

                        'size' =>
                            (int) (
                                $this
                                    ->file
                                    ->size
                                ?? 0
                            ),

                        'checksum' =>
                            $this
                                ->file
                                ->checksum,

                        'expires_at' =>
                            $this
                                ->file
                                ->expires_at
                                ?->toISOString(),

                        'download_ready' =>
                            ! $this
                                ->file
                                ->expires_at
                            || $this
                                ->file
                                ->expires_at
                                ->isFuture(),
                    ]
                ),

            'created_at' =>
                $this
                    ->created_at
                    ?->toISOString(),

            'updated_at' =>
                $this
                    ->updated_at
                    ?->toISOString(),
        ];
    }
}
