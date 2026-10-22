<?php

namespace Tests\Unit\Support;

use App\Support\Input\DigitNormalizer;
use PHPUnit\Framework\TestCase;

final class DigitNormalizerTest extends TestCase
{
    public function test_it_converts_persian_and_arabic_indic_digits(): void
    {
        $normalizer = new DigitNormalizer();

        $this->assertSame(
            '09123456789 | 0123456789',
            $normalizer->normalize(
                '۰۹۱۲۳۴۵۶۷۸۹ | ٠١٢٣٤٥٦٧٨٩'
            )
        );
    }

    public function test_it_preserves_non_digit_characters(): void
    {
        $normalizer = new DigitNormalizer();

        $this->assertSame(
            'بلوک 12-A / واحد 305',
            $normalizer->normalize(
                'بلوک ۱۲-A / واحد ۳۰۵'
            )
        );
    }
}
