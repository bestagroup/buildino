<?php

namespace Tests\Feature\System;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class PortalBladeAttributeIntegrityTest extends TestCase
{
    public function test_blade_expressions_do_not_contain_injected_html_attributes(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                resource_path('views/portal'),
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        $violations = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (
                ! $file->isFile()
                || ! str_ends_with(
                    $file->getFilename(),
                    '.blade.php'
                )
            ) {
                continue;
            }

            $contents = file_get_contents(
                $file->getPathname()
            );

            if ($contents === false) {
                continue;
            }

            if (
                preg_match(
                    '/\{\{(?:(?!\}\}).)*\bclass\s*=/s',
                    $contents,
                    $match,
                    PREG_OFFSET_CAPTURE
                ) !== 1
            ) {
                continue;
            }

            $offset = $match[0][1];
            $line = substr_count(
                substr($contents, 0, $offset),
                "\n"
            ) + 1;

            $violations[] = sprintf(
                '%s:%d',
                str_replace(
                    base_path() . DIRECTORY_SEPARATOR,
                    '',
                    $file->getPathname()
                ),
                $line
            );
        }

        $this->assertSame(
            [],
            $violations,
            'HTML attributes were injected inside a Blade echo expression: '
                . implode(', ', $violations)
        );
    }
}
