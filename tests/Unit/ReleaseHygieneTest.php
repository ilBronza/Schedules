<?php

namespace IlBronza\Schedules\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

class ReleaseHygieneTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function testPlaceholderLicenseMarkdownIsAbsent(): void
    {
        $this->assertFileDoesNotExist(
            $this->root.'/license.md',
            'Placeholder license.md must be removed; keep LICENSE only.'
        );
    }

    public function testMitLicenseFileIsPresent(): void
    {
        $license = $this->root.'/LICENSE';

        $this->assertFileExists($license);
        $this->assertStringContainsString(
            'MIT License',
            file_get_contents($license)
        );
    }

    public function testComposerDeclaresActivitylogWhenUsedDirectly(): void
    {
        $usesActivitylog = $this->packageUsesSpatieActivitylogDirectly();

        if (! $usesActivitylog) {
            $this->assertTrue(true);

            return;
        }

        $composer = $this->composerJson();
        $declared = array_merge(
            array_keys($composer['require'] ?? []),
            array_keys($composer['require-dev'] ?? [])
        );

        $this->assertContains(
            'spatie/laravel-activitylog',
            $declared,
            'Direct Spatie Activitylog usage must be declared in composer.json'
        );
    }

    public function testGitignoreCoversTestbenchCacheAndLogs(): void
    {
        $gitignore = file_get_contents($this->root.'/.gitignore');

        $this->assertNotFalse($gitignore);

        foreach ([
            'tests/Fixtures/App/bootstrap/cache',
            'tests/Fixtures/App/storage/logs',
            '.ibphp-tmp',
        ] as $pattern) {
            $this->assertTrue(
                $this->gitignoreMatches($gitignore, $pattern),
                ".gitignore must ignore generated path [{$pattern}]"
            );
        }
    }

    public function testChangelogDocumentsCurrentRelease(): void
    {
        $changelog = file_get_contents($this->root.'/changelog.md');

        $this->assertNotFalse($changelog);
        $this->assertDoesNotMatchRegularExpression(
            '/^### Added\s*\n-\s*Everything\s*$/m',
            $changelog,
            'changelog.md must describe real release changes'
        );
        $this->assertMatchesRegularExpression(
            '/upgrade|v2\\.5\\.1|evaluator|lifecycle/i',
            $changelog
        );
    }

    public function testUpgradeGuideExists(): void
    {
        $upgrading = $this->root.'/UPGRADING.md';

        $this->assertFileExists($upgrading);
        $contents = file_get_contents($upgrading);
        $this->assertStringContainsString('v2.5.1', $contents);
        $this->assertMatchesRegularExpression(
            '/migrate|migration/i',
            $contents
        );
    }

    public function testReadmeDocumentsHostIntegrationSurface(): void
    {
        $readme = file_get_contents($this->root.'/readme.md');

        $this->assertNotFalse($readme);

        foreach ([
            'InteractsWithSchedule',
            'Schedulable',
            'schedule:run',
            'defaultRoles',
            'notifications',
            'Soft delete',
        ] as $needle) {
            $this->assertStringContainsString(
                $needle,
                $readme,
                "readme.md must document [{$needle}]"
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function composerJson(): array
    {
        $path = $this->root.'/composer.json';
        $decoded = json_decode(file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException(
                'composer.json is not valid JSON: '.$path
            );
        }

        return $decoded;
    }

    private function packageUsesSpatieActivitylogDirectly(): bool
    {
        $roots = [
            $this->root.'/src',
            $this->root.'/tests',
        ];

        foreach ($roots as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory)
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());

                if ($contents === false) {
                    throw new RuntimeException(
                        'Unable to read '.$file->getPathname()
                    );
                }

                if (preg_match(
                    '/\\\\?Spatie\\\\Activitylog\\\\|use Spatie\\\\Activitylog/',
                    $contents
                )) {
                    return true;
                }
            }
        }

        return false;
    }

    private function gitignoreMatches(string $gitignore, string $needle): bool
    {
        foreach (preg_split('/\R/', $gitignore) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, $needle)) {
                return true;
            }
        }

        return false;
    }
}
