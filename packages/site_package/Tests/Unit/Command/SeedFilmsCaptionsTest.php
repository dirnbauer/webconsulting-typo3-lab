<?php
declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Webconsulting\SitePackage\Command\SeedFilmsCommand;

final class SeedFilmsCaptionsTest extends TestCase
{
    public function testLegacyFilmsKeepTheirSingleTrack(): void
    {
        self::assertSame(['old.en.vtt'], SeedFilmsCommand::captionFilesOf(['vtt' => 'old.en.vtt']));
    }

    public function testAllLanguageTracksKeepManifestOrder(): void
    {
        self::assertSame(['film.en.vtt', 'film.de.vtt', 'film.zh.vtt', 'film.hu.vtt'], SeedFilmsCommand::captionFilesOf([
            'vtt' => 'film.en.vtt',
            'captionTracks' => ['en' => 'film.en.vtt', 'de' => 'film.de.vtt', 'zh' => 'film.zh.vtt', 'hu' => 'film.hu.vtt'],
        ]));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidTracks(): iterable
    {
        yield 'missing captions' => [[]];
        yield 'not an array' => [['captionTracks' => 'film.en.vtt']];
        yield 'empty tracks' => [['captionTracks' => []]];
        yield 'path outside fixture' => [['captionTracks' => ['en' => '../film.en.vtt']]];
        yield 'wrong format' => [['captionTracks' => ['en' => 'film.en.srt']]];
        yield 'duplicate track' => [['captionTracks' => ['en' => 'film.en.vtt', 'de' => 'film.en.vtt']]];
        yield 'more than field limit' => [['captionTracks' => array_map(static fn(int $i): string => $i . '.en.vtt', range(1, 11))]];
    }

    /** @param array<string, mixed> $film */
    #[DataProvider('invalidTracks')]
    public function testInvalidCaptionsFailBeforeSeeding(array $film): void
    {
        $this->expectException(\RuntimeException::class);
        SeedFilmsCommand::captionFilesOf($film);
    }
}
