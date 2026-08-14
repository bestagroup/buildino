<?php

namespace App\Support\Jalali;

use DateTimeInterface;
use IntlDateFormatter;
use Throwable;

final class JalaliDateFormatter
{
    public function date(
        ?DateTimeInterface $date
    ): ?string {
        return $this->format(
            $date,
            'Y/m/d',
            'yyyy/MM/dd'
        );
    }

    public function dateTime(
        ?DateTimeInterface $date
    ): ?string {
        return $this->format(
            $date,
            'Y/m/d H:i',
            'yyyy/MM/dd - HH:mm'
        );
    }

    public function source(): string
    {
        return class_exists(
            \Morilog\Jalali\Jalalian::class
        )
            ? 'morilog/jalali'
            : (
                class_exists(
                    IntlDateFormatter::class
                )
                    ? 'intl-persian'
                    : 'gregorian-fallback'
            );
    }

    private function format(
        ?DateTimeInterface $date,
        string $morilogFormat,
        string $intlPattern
    ): ?string {
        if (! $date) {
            return null;
        }

        if (
            class_exists(
                \Morilog\Jalali\Jalalian::class
            )
        ) {
            try {
                return \Morilog\Jalali\Jalalian::fromDateTime(
                    $date
                )->format(
                    $morilogFormat
                );
            } catch (Throwable) {
                // Continue to the framework-independent fallback.
            }
        }

        if (
            class_exists(
                IntlDateFormatter::class
            )
        ) {
            try {
                $formatter =
                    new IntlDateFormatter(
                        'fa_IR@calendar=persian',
                        IntlDateFormatter::NONE,
                        IntlDateFormatter::NONE,
                        'Asia/Tehran',
                        IntlDateFormatter::TRADITIONAL,
                        $intlPattern
                    );

                $formatted =
                    $formatter->format(
                        $date
                    );

                if (
                    $formatted !== false
                ) {
                    return $formatted;
                }
            } catch (Throwable) {
                // Continue to the final safe fallback.
            }
        }

        return $date->format(
            str_contains(
                $morilogFormat,
                'H:i'
            )
                ? 'Y/m/d - H:i'
                : 'Y/m/d'
        );
    }
}
