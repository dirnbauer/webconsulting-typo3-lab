<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Translation;

use PHPUnit\Framework\TestCase;
use Webconsulting\SitePackage\Translation\TranslationMemory;

/**
 * A memory is `<lang>.json` plus the fragments in `<lang>.d/*.json`.
 */
final class TranslationMemoryFragmentsTest extends TestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/memory-' . bin2hex(random_bytes(4));
        mkdir($this->directory . '/de.d', 0777, true);
        $this->write('de.json', ['Quality' => 'Qualität', 'Read more' => 'Weiterlesen']);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directory . '/de.d/*') ?: []);
        rmdir($this->directory . '/de.d');
        array_map('unlink', glob($this->directory . '/*.json') ?: []);
        rmdir($this->directory);
    }

    /**
     * @param array<string, string> $strings
     */
    private function write(string $file, array $strings, string $language = 'de'): void
    {
        file_put_contents($this->directory . '/' . $file, json_encode(['language' => $language, 'strings' => $strings], JSON_UNESCAPED_UNICODE));
    }

    public function testFragmentsAddTheirEntriesInFileNameOrder(): void
    {
        $this->write('de.d/b-talk.json', ['Agent paths' => 'Agentenwege']);
        $this->write('de.d/a-other.json', ['Answers and limits' => 'Antworten und Grenzen']);
        self::assertSame([$this->directory . '/de.d/a-other.json', $this->directory . '/de.d/b-talk.json'], TranslationMemory::fragmentPaths($this->directory . '/de.json', 'de'));

        $memory = TranslationMemory::fromFiles($this->directory . '/de.json', 'de');
        self::assertSame(4, $memory->count());
        self::assertSame('Agentenwege', $memory->translate('Agent  paths'));
        self::assertSame('Weiterlesen', $memory->translate('Read more'));
    }

    public function testTheSameTranslationTwiceIsFine(): void
    {
        $this->write('de.d/talk.json', ['Quality' => 'Qualität', ' <p>Source</p> ' => '<p>Quelle</p>']);
        $this->write('de.d/zz.json', ['<p>Source</p>' => '<p>Quelle</p>']);

        self::assertSame(3, TranslationMemory::fromFiles($this->directory . '/de.json', 'de')->count());
    }

    public function testADifferentTranslationOfTheSameSourceIsAnError(): void
    {
        $this->write('de.d/talk.json', ['Quality ' => 'Güte']);
        $this->expectExceptionCode(1790100003);
        $this->expectExceptionMessage('de.d/talk.json translates "Quality " differently than de.json.');
        TranslationMemory::fromFiles($this->directory . '/de.json', 'de');
    }

    public function testAConflictBetweenTwoFragmentsIsAnError(): void
    {
        $this->write('de.d/a.json', ['Agent paths' => 'Agentenwege']);
        $this->write('de.d/b.json', ['Agent paths' => 'Wege der Agenten']);
        $this->expectExceptionCode(1790100003);
        TranslationMemory::fromFiles($this->directory . '/de.json', 'de');
    }

    public function testAFragmentInAnotherLanguageIsRejected(): void
    {
        $this->write('de.d/talk.json', ['Agent paths' => 'Agentenwege'], 'hu');
        $this->expectExceptionCode(1790100002);
        TranslationMemory::fromFiles($this->directory . '/de.json', 'de');
    }

    public function testWithoutFragmentsItIsTheSingleFile(): void
    {
        self::assertSame([], TranslationMemory::fragmentPaths($this->directory . '/de.json', 'de'));
        self::assertSame(2, TranslationMemory::fromFiles($this->directory . '/de.json', 'de')->count());
        self::assertSame(0, TranslationMemory::fromFiles($this->directory . '/zh.json', 'zh')->count());
    }
}
