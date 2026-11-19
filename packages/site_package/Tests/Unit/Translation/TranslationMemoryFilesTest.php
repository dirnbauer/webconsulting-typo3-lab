<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Translation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Webconsulting\SitePackage\Command\ContentTranslateCommand;
use Webconsulting\SitePackage\ContentAudit\Canon;
use Webconsulting\SitePackage\Translation\TranslationMemory;
use Webconsulting\SitePackage\Translation\TranslationScope;

/**
 * The shipped translation memories and their fragments (`<lang>.d/*.json`):
 * every translation keeps the markup, the code, the links and the
 * placeholders of its source, the German ones address the reader with "Sie",
 * and a fragment never contradicts the memory it extends.
 *
 * The one exception to "Sie": a quotation of third-party German that the
 * canon lists in a `quoted_copy` file with the rule `address` (the
 * Netresearch talk addresses its audience with "euch"). It is accepted in a
 * fragment only, for exactly that German text; the shared memories stay strict.
 */
final class TranslationMemoryFilesTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function memoryFiles(): iterable
    {
        foreach (glob(ContentTranslateCommand::MEMORY_DIRECTORY . '/*/*.json') ?: [] as $path) {
            if (basename($path) !== 'scope.json') {
                yield basename(dirname($path)) . '/' . basename($path) => [$path, basename($path, '.json')];
            }
        }
        foreach (glob(ContentTranslateCommand::MEMORY_DIRECTORY . '/*/*.d/*.json') ?: [] as $path) {
            yield basename(dirname($path, 2)) . '/' . basename(dirname($path)) . '/' . basename($path) => [$path, basename(dirname($path), '.d')];
        }
    }

    public function testFragmentsExtendTheirMemoryWithoutContradictingIt(): void
    {
        $memories = 0;
        foreach (iterator_to_array(self::memoryFiles()) as [$path, $language]) {
            if (str_ends_with(basename(dirname($path)), '.d')) {
                continue;
            }
            $memories++;
            // Throws on a fragment that translates a source differently.
            $memory = TranslationMemory::fromFiles($path, $language);
            self::assertGreaterThanOrEqual(TranslationMemory::fromFile($path, $language)->count(), $memory->count());
        }
        self::assertGreaterThan(0, $memories);
    }

    public function testQuotationsKeepingTheOriginalAddressAreNotInTheSharedMemories(): void
    {
        $canon = Canon::fromFile();
        $quotations = array_values(array_filter($canon->quotedCopy, static fn (array $entry): bool => in_array('address', $entry['rules'], true)));
        self::assertNotEmpty($quotations, 'the canon lists the quoted German that keeps "euch"');
        foreach (glob(ContentTranslateCommand::MEMORY_DIRECTORY . '/*/de.json') ?: [] as $path) {
            $translations = $this->strings($path, 'de');
            foreach ($quotations as $quotation) {
                self::assertSame('de', $quotation['language']);
                self::assertNotContains($quotation['text'], $translations, 'a quotation belongs in a fragment, not in ' . basename(dirname($path)) . '/de.json');
            }
        }
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function scopeFiles(): iterable
    {
        foreach (glob(ContentTranslateCommand::MEMORY_DIRECTORY . '/*/scope.json') ?: [] as $path) {
            yield basename(dirname($path)) => [$path];
        }
    }

    public function testEverySiteHasAScopeAndAMemory(): void
    {
        self::assertNotEmpty(iterator_to_array(self::scopeFiles()));
        self::assertNotEmpty(iterator_to_array(self::memoryFiles()));
    }

    #[DataProvider('scopeFiles')]
    public function testScopeFileIsReadable(string $path): void
    {
        $scope = TranslationScope::fromFile($path);
        $data = json_decode((string)file_get_contents($path), true);

        // The memory directory is the canon site key the command resolves --site to.
        self::assertIsArray($data);
        self::assertSame(basename(dirname($path)), $data['site'] ?? null);
        foreach ($scope->recordTables as $table) {
            self::assertMatchesRegularExpression('/^[a-z0-9_]+$/', $table);
        }
        foreach ($scope->skip as $rule) {
            self::assertGreaterThan(0, $rule['page']);
        }
    }

    #[DataProvider('memoryFiles')]
    public function testTranslationsKeepMarkupCodeAndLinks(string $path, string $language): void
    {
        $strings = $this->strings($path, $language);
        $problems = [];
        foreach ($strings as $source => $translation) {
            if (trim($translation) === '' && trim($source) !== '') {
                $problems[] = 'empty translation for: ' . mb_strimwidth($source, 0, 80, '…');
                continue;
            }
            foreach (['tags' => $this->tags(...), 'code' => $this->code(...), 'links' => $this->links(...)] as $what => $extract) {
                if ($extract($source) !== $extract($translation)) {
                    $problems[] = sprintf('%s differ in: %s', $what, mb_strimwidth($source, 0, 80, '…'));
                }
            }
        }

        self::assertSame([], $problems);
    }

    #[DataProvider('memoryFiles')]
    public function testGermanAddressesTheReaderWithSie(string $path, string $language): void
    {
        if ($language !== 'de') {
            self::assertNotSame('de', $language);
            return;
        }
        $canon = Canon::fromFile();
        $pattern = $canon->address['sie'] ?? '';
        self::assertNotSame('', $pattern);
        $fragment = str_ends_with(basename(dirname($path)), '.d');
        $informal = [];
        foreach ($this->strings($path, $language) as $translation) {
            if ($fragment && in_array('address', $canon->quotedRules($translation, 'de'), true)) {
                continue;
            }
            if (preg_match('/' . $pattern . '/u', strip_tags($translation), $match) === 1) {
                $informal[] = $match[0] . ' in: ' . mb_strimwidth(strip_tags($translation), 0, 80, '…');
            }
        }

        self::assertSame([], $informal);
    }

    /**
     * @return array<string, string>
     */
    private function strings(string $path, string $language): array
    {
        $data = json_decode((string)file_get_contents($path), true);
        self::assertIsArray($data, $path . ' is valid JSON');
        self::assertSame($language, $data['language'] ?? null, 'the file name is the language');
        self::assertIsArray($data['strings'] ?? null);
        self::assertSame(count($data['strings']), TranslationMemory::fromFile($path, $language)->count(), 'no two sources differ only in whitespace or link targets');
        $strings = [];
        foreach ($data['strings'] as $source => $translation) {
            self::assertIsString($translation);
            $strings[(string)$source] = $translation;
        }

        return $strings;
    }

    /**
     * Tags with their attributes, except those that hold text.
     *
     * @return list<string>
     */
    private function tags(string $html): array
    {
        preg_match_all('/<\s*(\/?)\s*([a-zA-Z][a-zA-Z0-9]*)([^>]*)>/', $html, $matches, PREG_SET_ORDER);
        $tags = [];
        foreach ($matches as $match) {
            preg_match_all('/([a-zA-Z-]+)\s*=\s*("[^"]*"|\'[^\']*\')/', $match[3], $attributes, PREG_SET_ORDER);
            $kept = [];
            foreach ($attributes as $attribute) {
                if (!in_array(strtolower($attribute[1]), ['title', 'alt', 'aria-label'], true)) {
                    $kept[] = $attribute[1] . '=' . $attribute[2];
                }
            }
            sort($kept);
            $tags[] = $match[1] . strtolower($match[2]) . ' ' . implode(' ', $kept);
        }
        sort($tags);

        return $tags;
    }

    /**
     * @return list<string>
     */
    private function code(string $html): array
    {
        preg_match_all('/<code[^>]*>(.*?)<\/code>/is', $html, $matches);
        $code = $matches[1];
        sort($code);

        return $code;
    }

    /**
     * @return list<string>
     */
    private function links(string $html): array
    {
        preg_match_all('#(?:https?|t3|mailto):[^\s"<>)]+#', $html, $matches);
        $links = $matches[0];
        sort($links);

        return $links;
    }
}
