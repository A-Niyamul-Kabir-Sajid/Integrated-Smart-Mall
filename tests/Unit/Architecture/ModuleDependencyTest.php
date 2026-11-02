<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Enforces the mechanical rules of DOCS/11_MODULE_CONTRACTS.md.
 */
class ModuleDependencyTest extends TestCase
{
    private const ORCHESTRATING_MODULES = ['ShopOwnerOperations', 'MallOwnerAdmin'];

    public function test_no_module_uses_another_modules_http_layer(): void
    {
        foreach ($this->moduleSources() as [$module, $path, $source]) {
            preg_match_all('/App\\\\Modules\\\\(\w+)\\\\Http\\\\/', $source, $matches);

            foreach (array_unique($matches[1]) as $referencedModule) {
                $this->assertSame($module, $referencedModule, "{$path} uses the Http layer of {$referencedModule}.");
            }
        }
    }

    public function test_data_owning_modules_do_not_depend_on_orchestrating_modules(): void
    {
        foreach ($this->moduleSources() as [$module, $path, $source]) {
            if (in_array($module, self::ORCHESTRATING_MODULES, true)) {
                continue;
            }

            foreach (self::ORCHESTRATING_MODULES as $orchestrator) {
                $this->assertStringNotContainsString("App\\Modules\\{$orchestrator}\\", $source, "{$path} must not depend on {$orchestrator}.");
            }
        }
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}> [module, relative path, source]
     */
    private function moduleSources(): array
    {
        $modulesPath = dirname(__DIR__, 3).'/app/Modules';
        $sources = [];

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modulesPath, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($files as $file) {
            /** @var SplFileInfo $file */
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen($modulesPath) + 1));
            $sources[] = [strtok($relativePath, '/'), $relativePath, file_get_contents($file->getPathname())];
        }

        return $sources;
    }
}
