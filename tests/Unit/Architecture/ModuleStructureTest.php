<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class ModuleStructureTest extends TestCase
{
    /**
     * The application modules defined in DOCS/05_MODULE_MAP.md.
     */
    private const MODULES = [
        'AccountsAccess',
        'AnalyticsReports',
        'ChatbotRag',
        'FavouritesPreferences',
        'MallOwnerAdmin',
        'MallShopDirectory',
        'MapsNavigation',
        'ParkingFacilities',
        'ProductsCatalogue',
        'PurchaseVerification',
        'ReviewsModeration',
        'SharedPlatform',
        'ShopOwnerOperations',
    ];

    public function test_app_modules_contains_exactly_the_mapped_modules(): void
    {
        $directories = array_map('basename', glob($this->basePath('app/Modules/*'), GLOB_ONLYDIR));

        $this->assertSame(self::MODULES, $directories);
    }

    public function test_every_module_documents_its_ownership(): void
    {
        foreach (self::MODULES as $module) {
            $readme = $this->basePath("app/Modules/{$module}/README.md");

            $this->assertFileExists($readme);
            $this->assertStringContainsString("App\\Modules\\{$module}", file_get_contents($readme), "{$module} README must state its namespace.");
        }
    }

    public function test_class_files_declare_the_namespace_matching_their_path(): void
    {
        foreach ($this->phpFilesUnder('app') as $file) {
            $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen($this->basePath('app')) + 1));
            $expectedNamespace = 'App'.str_replace('/', '\\', '/'.dirname($relativePath));
            $expectedNamespace = rtrim(str_replace('\\.', '', $expectedNamespace), '\\');

            $source = file_get_contents($file->getPathname());

            $this->assertMatchesRegularExpression(
                '/^namespace '.preg_quote($expectedNamespace, '/').';$/m',
                $source,
                "{$relativePath} must declare namespace {$expectedNamespace}.",
            );
        }
    }

    /**
     * @return list<SplFileInfo>
     */
    private function phpFilesUnder(string $directory): array
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->basePath($directory), RecursiveDirectoryIterator::SKIP_DOTS));

        return array_values(array_filter(
            iterator_to_array($files, false),
            fn (SplFileInfo $file): bool => $file->getExtension() === 'php',
        ));
    }

    private function basePath(string $path = ''): string
    {
        return dirname(__DIR__, 3).($path === '' ? '' : '/'.$path);
    }
}
