<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Deployment;

use PHPUnit\Framework\TestCase;
use Webconsulting\Lab\Build\CiDropPrivatePackages;
use Webconsulting\Lab\Build\PatchDeclarations;

/**
 * The static patch guard (Build/Scripts/check-patch-declarations.php) runs in
 * GitHub CI before the Powermail packages are dropped, so it is the only CI
 * check that sees the five in2code patches at all. It needs no package code.
 */
final class PatchDeclarationsTest extends TestCase
{
    private const VALID_PATCH = <<<'DIFF'
        diff --git a/Classes/Example.php b/Classes/Example.php
        --- a/Classes/Example.php
        +++ b/Classes/Example.php
        @@ -1,3 +1,3 @@
         <?php
        -return 1;
        +return 2;
         // end
        diff --git a/Configuration/New.yaml b/Configuration/New.yaml
        new file mode 100644
        --- /dev/null
        +++ b/Configuration/New.yaml
        @@ -0,0 +1,2 @@
        +name: example
        +label: Example
        \ No newline at end of file

        DIFF;

    private string $root = '';

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 3) . '/Build/Scripts/check-patch-declarations.php';
    }

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/patch-declarations-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/patches', 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/patches/*') ?: [] as $file) {
            unlink($file);
        }
        foreach (glob($this->root . '/*') ?: [] as $file) {
            is_dir($file) ? rmdir($file) : unlink($file);
        }
        rmdir($this->root);
    }

    /**
     * @param array<string, string> $patches path => content
     * @param array<string, mixed> $lockOverrides
     */
    private function project(array $patches, array $lockOverrides = []): void
    {
        $declared = [];
        $locked = [];
        foreach ($patches as $path => $content) {
            file_put_contents($this->root . '/' . $path, $content);
            $declared['in2code/powermail']['Fix ' . basename($path)] = $path;
            $locked['in2code/powermail'][] = array_merge([
                'package' => 'in2code/powermail',
                'description' => 'Fix ' . basename($path),
                'url' => $path,
                'sha256' => hash('sha256', $content),
                'depth' => 1,
                'extra' => ['provenance' => 'root'],
            ], $lockOverrides);
        }
        file_put_contents($this->root . '/composer.json', CiDropPrivatePackages::encode(['extra' => ['patches' => $declared]], '    '));
        $lock = ['_hash' => '', 'patches' => $locked];
        $lock['_hash'] = CiDropPrivatePackages::patchesLockHash(CiDropPrivatePackages::encode($lock, '    '));
        file_put_contents($this->root . '/patches.lock.json', CiDropPrivatePackages::encode($lock, '    '));
    }

    public function testTheRepositoryItselfPasses(): void
    {
        $root = dirname(__DIR__, 3);
        $problems = PatchDeclarations::check($root);
        if (!str_contains((string)file_get_contents($root . '/composer.json'), 'in2code/powermail')) {
            // GitHub CI after ci-drop-private-packages.php: the in2code patch
            // files stay, their declarations are gone. The Quality job runs this
            // guard before the drop, where they are declared.
            $problems = array_values(array_filter(
                $problems,
                static fn(string $problem): bool => !str_starts_with($problem, 'patches/in2code-powermail'),
            ));
        }
        self::assertSame([], $problems);
    }

    public function testCiRunsTheGuardBeforeTheDrop(): void
    {
        $workflow = (string)file_get_contents(dirname(__DIR__, 3) . '/.github/workflows/quality.yml');
        $guard = strpos($workflow, 'run: php Build/Scripts/check-patch-declarations.php');
        $drop = strpos($workflow, 'run: php Build/Scripts/ci-drop-private-packages.php');
        self::assertIsInt($guard, 'The Quality job must check the patch declarations.');
        self::assertIsInt($drop);
        self::assertLessThan($drop, $guard, 'The guard must see the in2code patches, so it runs before the drop.');
    }

    public function testAConsistentProjectPasses(): void
    {
        $this->project(['patches/in2code-powermail-example.patch' => self::VALID_PATCH]);

        self::assertSame([], PatchDeclarations::check($this->root));
    }

    public function testAChangedPatchWithoutRelockFails(): void
    {
        $this->project(['patches/in2code-powermail-example.patch' => self::VALID_PATCH]);
        file_put_contents($this->root . '/patches/in2code-powermail-example.patch', str_replace('return 2;', 'return 3;', self::VALID_PATCH));

        $problems = PatchDeclarations::check($this->root);
        self::assertCount(1, $problems);
        self::assertStringContainsString('patches-relock', $problems[0]);
    }

    public function testAMissingPatchFileFails(): void
    {
        $this->project(['patches/in2code-powermail-example.patch' => self::VALID_PATCH]);
        unlink($this->root . '/patches/in2code-powermail-example.patch');

        self::assertStringContainsString('does not exist', implode("\n", PatchDeclarations::check($this->root)));
    }

    public function testADeclaredButUnlockedPatchAndAStaleLockEntryFail(): void
    {
        $this->project(['patches/in2code-powermail-example.patch' => self::VALID_PATCH]);
        $composer = json_decode((string)file_get_contents($this->root . '/composer.json'), true);
        self::assertIsArray($composer);
        $composer['extra']['patches']['in2code/powermail'] = ['Renamed' => 'patches/in2code-powermail-other.patch'];
        file_put_contents($this->root . '/composer.json', json_encode($composer));
        file_put_contents($this->root . '/patches/in2code-powermail-other.patch', self::VALID_PATCH);

        $problems = implode("\n", PatchDeclarations::check($this->root));
        self::assertStringContainsString('in2code-powermail-other.patch) is declared in composer.json but not in patches.lock.json', $problems);
        self::assertStringContainsString('in2code-powermail-example.patch), which composer.json does not declare', $problems);
        self::assertStringContainsString('patches/in2code-powermail-example.patch is not declared', $problems);
    }

    public function testAHandEditedLockHashFails(): void
    {
        $this->project(['patches/in2code-powermail-example.patch' => self::VALID_PATCH]);
        $lock = (string)file_get_contents($this->root . '/patches.lock.json');
        file_put_contents($this->root . '/patches.lock.json', str_replace('"depth": 1', '"depth": 2', $lock));

        self::assertStringContainsString('"_hash"', implode("\n", PatchDeclarations::check($this->root)));
    }

    public function testAnUndeclaredPatchFileFails(): void
    {
        $this->project(['patches/in2code-powermail-example.patch' => self::VALID_PATCH]);
        file_put_contents($this->root . '/patches/forgotten.patch', self::VALID_PATCH);

        self::assertSame(['patches/forgotten.patch is not declared in composer.json extra.patches (declare it or move it out of patches/).'], PatchDeclarations::check($this->root));
    }

    public function testAWellFormedDiffHasNoProblems(): void
    {
        self::assertSame([], PatchDeclarations::diffProblems(self::VALID_PATCH, 1));
    }

    public function testAHunkWithWrongCountsIsReported(): void
    {
        $diff = str_replace('@@ -1,3 +1,3 @@', '@@ -1,4 +1,3 @@', self::VALID_PATCH);

        self::assertNotSame([], PatchDeclarations::diffProblems($diff, 1));
    }

    public function testATruncatedDiffIsReported(): void
    {
        $diff = substr(self::VALID_PATCH, 0, (int)strpos(self::VALID_PATCH, ' // end'));

        self::assertStringContainsString('ends early', implode("\n", PatchDeclarations::diffProblems($diff, 1)));
    }

    public function testPathsThatLeaveThePackageOrNeedAnotherDepthAreReported(): void
    {
        $escape = str_replace('a/Classes/Example.php', 'a/../Example.php', self::VALID_PATCH);
        self::assertStringContainsString('leaves the package', implode("\n", PatchDeclarations::diffProblems($escape, 1)));

        self::assertStringContainsString('fewer than', implode("\n", PatchDeclarations::diffProblems(self::VALID_PATCH, 3)));
    }

    public function testANewFileWithExistingLinesAndARenameAreReported(): void
    {
        $newFile = str_replace('@@ -0,0 +1,2 @@', '@@ -1,0 +1,2 @@', self::VALID_PATCH);
        self::assertStringContainsString('is a new file', implode("\n", PatchDeclarations::diffProblems($newFile, 1)));

        $rename = str_replace('+++ b/Classes/Example.php', '+++ b/Classes/Other.php', self::VALID_PATCH);
        self::assertStringContainsString('name different files', implode("\n", PatchDeclarations::diffProblems($rename, 1)));
    }

    public function testTextWithoutADiffIsReported(): void
    {
        self::assertSame(['it contains no unified diff ("--- " and "+++ " file headers).'], PatchDeclarations::diffProblems("just text\n", 1));
    }
}
