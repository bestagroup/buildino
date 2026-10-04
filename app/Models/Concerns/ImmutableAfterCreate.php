<?php

namespace App\Models\Concerns;

use LogicException;

trait ImmutableAfterCreate
{
    protected static function bootImmutableAfterCreate(): void
    {
        static::updating(function (): void {
            throw new LogicException(
                sprintf(
                    '%s is immutable after creation.',
                    class_basename(static::class)
                )
            );
        });

        static::deleting(function (): void {
            throw new LogicException(
                sprintf(
                    '%s is immutable and cannot be deleted.',
                    class_basename(static::class)
                )
            );
        });
    }
}
