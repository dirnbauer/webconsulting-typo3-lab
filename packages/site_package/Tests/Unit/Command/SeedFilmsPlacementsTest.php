<?php
declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Webconsulting\SitePackage\Command\SeedFilmsCommand;

/**
 * film.json `placements` (sitepackage:films:seed): how they are read and which
 * marker each placement's elements carry. No database.
 */
final class SeedFilmsPlacementsTest extends TestCase
{
    public function testWithoutPlacementsTheFilmGoesOnItsSlugAfterTheIntro(): void
    {
        self::assertSame(
            [['slug' => '/features/docx-editor', 'afterHeader' => null, 'afterFilm' => null]],
            SeedFilmsCommand::placementsOf(['id' => 'docx-editor', 'slug' => '/features/docx-editor']),
        );
    }

    public function testPlacementsReplaceTheSlugAndKeepTheirOrder(): void
    {
        $film = [
            'id' => 'typo3-v14-strategy',
            'slug' => '/ignored',
            'placements' => [
                ['slug' => '/typo3-v14-strategy'],
                ['slug' => '/', 'after' => ['header' => 'Five points for every website agents visit']],
            ],
        ];

        self::assertSame([
            ['slug' => '/typo3-v14-strategy', 'afterHeader' => null, 'afterFilm' => null],
            ['slug' => '/', 'afterHeader' => 'Five points for every website agents visit', 'afterFilm' => null],
        ], SeedFilmsCommand::placementsOf($film));
    }

    public function testTheSameFilmMayFollowTwoHeadersOnOnePage(): void
    {
        $placements = SeedFilmsCommand::placementsOf(['id' => 'x', 'placements' => [
            ['slug' => '/', 'after' => ['header' => 'One']],
            ['slug' => '/', 'after' => ['header' => 'Two']],
        ]]);

        self::assertCount(2, $placements);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidFilms(): iterable
    {
        yield 'no slug, no placements' => [['id' => 'x']];
        yield 'relative slug' => [['id' => 'x', 'slug' => 'features/x']];
        yield 'empty placements' => [['id' => 'x', 'slug' => '/x', 'placements' => []]];
        yield 'placements as a map' => [['id' => 'x', 'placements' => ['home' => ['slug' => '/']]]];
        yield 'placement without slug' => [['id' => 'x', 'placements' => [['after' => ['header' => 'A']]]]];
        yield 'placement with a relative slug' => [['id' => 'x', 'placements' => [['slug' => 'x']]]];
        yield 'after without header' => [['id' => 'x', 'placements' => [['slug' => '/', 'after' => []]]]];
        yield 'after with an empty header' => [['id' => 'x', 'placements' => [['slug' => '/', 'after' => ['header' => '  ']]]]];
        yield 'after as text' => [['id' => 'x', 'placements' => [['slug' => '/', 'after' => 'Five points']]]];
        yield 'a placement twice' => [['id' => 'x', 'placements' => [['slug' => '/a'], ['slug' => '/a']]]];
        yield 'after a film and a header at once' => [['id' => 'x', 'placements' => [['slug' => '/', 'after' => ['header' => 'A', 'film' => 'y']]]]];
        yield 'after an unknown anchor kind' => [['id' => 'x', 'placements' => [['slug' => '/', 'after' => ['uid' => 12]]]]];
        yield 'after a film without id' => [['id' => 'x', 'placements' => [['slug' => '/', 'after' => ['film' => '']]]]];
        yield 'after a film id that is not one' => [['id' => 'x', 'placements' => [['slug' => '/', 'after' => ['film' => 'Lab Overview']]]]];
        yield 'after itself' => [['id' => 'x', 'placements' => [['slug' => '/', 'after' => ['film' => 'x']]]]];
        yield 'after the same film twice' => [['id' => 'x', 'placements' => [['slug' => '/', 'after' => ['film' => 'y']], ['slug' => '/', 'after' => ['film' => 'y']]]]];
    }

    /**
     * @param array<string, mixed> $film
     */
    #[DataProvider('invalidFilms')]
    public function testInvalidPlacementsStopTheFilm(array $film): void
    {
        $this->expectException(\RuntimeException::class);
        SeedFilmsCommand::placementsOf($film);
    }

    public function testAPlacementMayFollowAnotherFilmsTranscript(): void
    {
        self::assertSame(
            [['slug' => '/', 'afterHeader' => null, 'afterFilm' => 'lab-overview']],
            SeedFilmsCommand::placementsOf(['id' => 'usability-slogans', 'placements' => [['slug' => '/', 'after' => ['film' => 'lab-overview']]]]),
        );
    }

    public function testTheSameTextAsHeaderAndAsFilmAreTwoPlacements(): void
    {
        $placements = SeedFilmsCommand::placementsOf(['id' => 'x', 'placements' => [
            ['slug' => '/', 'after' => ['header' => 'lab-overview']],
            ['slug' => '/', 'after' => ['film' => 'lab-overview']],
        ]]);

        self::assertCount(2, $placements);
    }

    public function testTheVideoOptionsDefaultToAnAutoplayingWideFilm(): void
    {
        self::assertSame(
            ['variant' => 'default', 'autoplay' => true, 'eyebrow' => 'Film', 'caption' => ''],
            SeedFilmsCommand::videoOptionsOf(['id' => 'docx-editor', 'slug' => '/features/docx-editor']),
        );
    }

    public function testATalkSetsItsOwnLayoutEyebrowCaptionAndNoAutoplay(): void
    {
        self::assertSame(
            ['variant' => 'portrait', 'autoplay' => false, 'eyebrow' => 'From the founder', 'caption' => 'Presented by an AI avatar.'],
            SeedFilmsCommand::videoOptionsOf([
                'id' => 'usability-slogans',
                'variant' => 'portrait',
                'autoplay' => false,
                'eyebrow' => 'From the founder',
                'caption' => 'Presented by an AI avatar.',
            ]),
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidVideoOptions(): iterable
    {
        yield 'unknown variant' => [['variant' => 'square']];
        yield 'variant as a number' => [['variant' => 1]];
        yield 'autoplay as text' => [['autoplay' => 'false']];
        yield 'autoplay as a number' => [['autoplay' => 0]];
        yield 'empty eyebrow' => [['eyebrow' => ' ']];
        yield 'caption as a list' => [['caption' => ['a']]];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('invalidVideoOptions')]
    public function testInvalidVideoOptionsStopTheFilm(array $options): void
    {
        $this->expectException(\RuntimeException::class);
        SeedFilmsCommand::videoOptionsOf(['id' => 'x', 'slug' => '/x'] + $options);
    }

    public function testFilmFoldersAreReadInSortedOrder(): void
    {
        $directory = sys_get_temp_dir() . '/seed-films-order-' . bin2hex(random_bytes(4));
        $manifests = [
            'usability-slogans/film.json',
            'usability-slogans/short/film.json',
            'usability-slogans/long/film.json',
            'lab-overview/film.json',
            'ai/film.json',
            'skill-typo3-rector/film.json',
        ];
        foreach ($manifests as $manifest) {
            is_dir($directory . '/' . dirname($manifest)) || mkdir($directory . '/' . dirname($manifest), 0777, true);
            file_put_contents($directory . '/' . $manifest, '{}');
        }
        mkdir($directory . '/no-manifest');
        try {
            $relative = array_map(static fn(string $file): string => substr($file, strlen($directory) + 1), SeedFilmsCommand::manifests($directory));
            self::assertSame(
                ['ai/film.json', 'lab-overview/film.json', 'skill-typo3-rector/film.json', 'usability-slogans/long/film.json', 'usability-slogans/short/film.json'],
                $relative,
                'cut folders are films of their own; a folder with cuts loses its own manifest during a change-over',
            );
        } finally {
            foreach ($manifests as $manifest) {
                unlink($directory . '/' . $manifest);
            }
            foreach (['usability-slogans/short', 'usability-slogans/long', 'usability-slogans', 'lab-overview', 'ai', 'skill-typo3-rector', 'no-manifest'] as $folder) {
                rmdir($directory . '/' . $folder);
            }
            rmdir($directory);
        }
    }

    public function testPageLinksBecomeT3LinksOfTheDefaultLanguagePage(): void
    {
        $asked = [];
        $pages = ['/usability-slogans' => 1290, '/' => 505];
        $lookup = static function (string $slug) use ($pages, &$asked): ?int {
            $asked[] = $slug;
            return $pages[$slug] ?? null;
        };

        self::assertSame(
            '<p><a href="t3://page?uid=1290">Watch the full talk</a> or <a href="t3://page?uid=1290">again</a>, <a href="t3://page?uid=505">home</a>.</p>',
            SeedFilmsCommand::resolvePageLinks('<p><a href="{{page:usability-slogans}}">Watch the full talk</a> or <a href="{{page:/usability-slogans}}">again</a>, <a href="{{page:}}">home</a>.</p>', $lookup),
        );
        self::assertSame(['/usability-slogans', '/usability-slogans', '/'], $asked);
    }

    public function testATextWithoutPageLinksStaysAsItIs(): void
    {
        $text = '<p><a href="https://www.nngroup.com/books/usability-engineering/">Nielsen</a> {{site:camp}}</p>';

        self::assertSame($text, SeedFilmsCommand::resolvePageLinks($text, static fn(string $slug): ?int => throw new \LogicException('no lookup expected')));
    }

    public function testAPageLinkToAMissingSlugStopsTheFilm(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('film.json links {{page:no-such-page}}, but the Desiderio site has no page with the slug /no-such-page.');

        SeedFilmsCommand::resolvePageLinks('<a href="{{page:no-such-page}}">x</a>', static fn(string $slug): ?int => null);
    }

    public function testTheFirstPlacementKeepsTheMarkersSeededBeforePlacementsExisted(): void
    {
        self::assertSame(['video' => 'docx-editor', 'transcript' => 'docx-editor-transcript'], SeedFilmsCommand::markerKeys('docx-editor', 0));
        self::assertSame(
            'Seeded by sitepackage:films:seed (docx-editor). A reseed replaces it.',
            SeedFilmsCommand::marker(SeedFilmsCommand::markerKeys('docx-editor', 0)['video']),
        );
    }

    public function testFurtherPlacementsAreNumberedFromTwo(): void
    {
        self::assertSame(['video' => 'typo3-v14-strategy#2', 'transcript' => 'typo3-v14-strategy#2-transcript'], SeedFilmsCommand::markerKeys('typo3-v14-strategy', 1));
        self::assertSame(['video' => 'typo3-v14-strategy#3', 'transcript' => 'typo3-v14-strategy#3-transcript'], SeedFilmsCommand::markerKeys('typo3-v14-strategy', 2));
    }

    public function testEveryMarkerOfAFilmIsUnique(): void
    {
        $markers = [];
        foreach (range(0, 4) as $placement) {
            foreach (SeedFilmsCommand::markerKeys('lab-overview', $placement) as $key) {
                $markers[] = SeedFilmsCommand::marker($key);
            }
        }

        self::assertSame($markers, array_unique($markers));
    }
}
