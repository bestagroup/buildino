<?php

namespace App\Services\Documents;

use App\Models\Building;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class DocumentTargetResolver
{
    /**
     * The public API accepts stable aliases and never arbitrary class names.
     *
     * @var array<string, class-string<Model>>
     */
    private const TARGETS = [
        'building' => Building::class,
        'unit' => Unit::class,
    ];

    public function resolve(string $type, int $id): Model
    {
        $modelClass = self::TARGETS[$type] ?? null;

        if ($modelClass === null) {
            throw ValidationException::withMessages([
                'documentable_type' => [
                    'نوع موجودیت سند پشتیبانی نمی‌شود.',
                ],
            ]);
        }

        $target = $modelClass::query()->find($id);

        if ($target === null) {
            throw ValidationException::withMessages([
                'documentable_id' => [
                    'موجودیت انتخاب‌شده برای سند یافت نشد.',
                ],
            ]);
        }

        return $target;
    }

    public function normalizedType(Model $target): string
    {
        return $target->getMorphClass();
    }
}
