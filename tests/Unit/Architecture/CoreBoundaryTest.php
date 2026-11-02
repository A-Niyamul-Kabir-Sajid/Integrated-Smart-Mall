<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class CoreBoundaryTest extends TestCase
{
    public function test_core_does_not_depend_on_any_module(): void
    {
        $coreFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 3).'/app/Core', RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($coreFiles as $file) {
            /** @var SplFileInfo $file */
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $this->assertStringNotContainsString(
                'App\\Modules\\',
                file_get_contents($file->getPathname()),
                $file->getFilename().' in app/Core must not reference a module.',
            );
        }
    }
}
