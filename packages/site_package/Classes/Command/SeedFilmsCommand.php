<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Puts the overview films (Build/Film, render-films.mjs) on their pages of
 * the Desiderio site: a Video Feature Section right after the page's hero
 * (or at the top of a page without one) and, below it, an accordion with the
 * transcript. On the Themes page, the site package's DesiderioThemes template
 * renders the two above the preset overview.
 *
 * Every film is a folder in Resources/Private/Fixtures/Films/ whose film.json
 * names the MP4, the WebP poster and the WebVTT captions and holds the page
 * slug, the texts and the transcript. One talk with several cuts keeps each
 * cut in its own folder below the film's folder (Films/usability-slogans/
 * short/film.json and long/film.json, each with its own id); a folder with
 * cut folders has no film.json of its own. The files go into FAL (fileadmin/
 * films/<id>/); a file with the same content there is reused.
 *
 * Placements: without `placements` the film goes on the page with its
 * `slug`, after the intro. With them it goes on every page listed:
 *
 *     "placements": [{"slug": "/typo3-v14-strategy"}, {"slug": "/", "after": {"header": "Five points …"}}]
 *
 * A placement with `after.header` puts the video right after the first
 * default-language element in colPos 0 of that page whose header is exactly
 * that text (the transcript after the video); without `after`, after the intro
 * as above. Each placement has its own markers: `<id>` for the first, `<id>#2`,
 * `<id>#3` … for the next ones (transcripts `<id>-transcript`, `<id>#2-transcript`).
 * The files are imported once per film id (fileadmin/films/<id>/) and reused.
 *
 * A placement with `after.film` (`{"slug": "/", "after": {"film": "lab-overview"}}`)
 * goes right after that film's transcript on the same page (the transcript
 * of its first placement, marker `<id>-transcript`). When that film is not on
 * the page, the placement fails with a message and the next film goes on; the
 * film folders are processed in sorted order, so a film that follows another
 * one in the same run finds it when its id sorts first.
 *
 * Optional film.json keys for the video element: `variant` ("default" or
 * "portrait", the Video Feature Section's layout), `autoplay` (true by default;
 * false starts the film on a click, with sound), `eyebrow` (default "Film")
 * and `caption` (the quiet note below the player, default empty).
 *
 * Links to pages of the Desiderio site in `description` and `caption` are
 * written as `{{page:<slug>}}` (`{{page:typo3-v14-strategy}}`); the seeder
 * replaces each with `t3://page?uid=<uid>` of the default-language page with
 * that slug, so TYPO3 renders it in the reader's language. A slug the site
 * does not have stops the film with an error.
 *
 * Idempotent by comparison: the two elements are found by the marker in
 * rowDescription, only values that differ are written, so a second run
 * reports 0 changes. A value edited in the backend is set back. Everything
 * goes through DataHandler in the live workspace; sitepackage:content:translate
 * then adds the de/zh/hu versions from the translation memories.
 *
 *     ddev exec vendor/bin/typo3 sitepackage:films:seed --dry-run
 *     ddev exec vendor/bin/typo3 sitepackage:films:seed --film=solr --pictures-only
 *     ddev exec vendor/bin/typo3 sitepackage:films:seed --film=docx-editor
 */
#[AsCommand(
    name: 'sitepackage:films:seed',
    description: 'Add the overview films (video after the hero, transcript below) to their Desiderio pages through DataHandler.',
)]
final class SeedFilmsCommand extends Command
{
    public const string FILMS = __DIR__ . '/../../Resources/Private/Fixtures/Films';
    private const string SITE = 'desiderio';
    private const string FOLDER = 'films';
    private const string TRANSCRIPT_HEADER = 'What the film says';
    private const string TRANSCRIPT_TITLE = 'Read the transcript';
    /** The Video Feature Section's layout select (Content Blocks prefixes it). */
    private const string VARIANT_FIELD = 'desiderio_featurevideo_variant';
    /** @var list<string> */
    private const array VARIANTS = ['default', 'portrait'];

    private bool $dryRun = false;
    private bool $picturesOnly = false;
    private int $rootUid = 0;
    private int $newCounter = 0;
    private OutputInterface $output;

    /** @var array<string, int> */
    private array $counts = ['created' => 0, 'updated' => 0, 'moved' => 0, 'files' => 0, 'imported' => 0];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly SiteFinder $siteFinder,
        private readonly StorageRepository $storageRepository,
    ) {
        parent::__construct();
        $this->output = new NullOutput();
    }

    protected function configure(): void
    {
        $this
            ->addOption('film', null, InputOption::VALUE_REQUIRED, 'Only these films (ids, comma-separated)')
            ->addOption('pictures-only', null, InputOption::VALUE_NONE, 'Replace MP4/poster files in existing references, preserving translated copy and captions')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would change, write nothing')
            ->addOption('allow-production', null, InputOption::VALUE_NONE, 'Allow running in the Production context');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->output = $output;
        $this->dryRun = (bool)$input->getOption('dry-run');
        $this->picturesOnly = (bool)$input->getOption('pictures-only');
        if (!$this->dryRun && Environment::getContext()->isProduction() && !$input->getOption('allow-production')) {
            $io->error('Refusing to write content in the Production context. Pass --allow-production if you mean it.');
            return Command::FAILURE;
        }
        $filter = $input->getOption('film');
        $only = is_string($filter) && $filter !== '' ? array_map('trim', explode(',', $filter)) : [];

        Bootstrap::initializeBackendAuthentication();
        $this->rootUid = $this->siteFinder->getSiteByIdentifier(self::SITE)->getRootPageId();

        $failed = false;
        foreach (self::manifests(self::FILMS) as $manifestFile) {
            $film = json_decode((string)file_get_contents($manifestFile), true);
            if (!is_array($film) || !is_string($film['id'] ?? null)) {
                $io->warning(sprintf('%s is not a film manifest.', $manifestFile));
                $failed = true;
                continue;
            }
            if ($only !== [] && !in_array($film['id'], $only, true)) {
                continue;
            }
            try {
                $this->seed($film, dirname($manifestFile));
            } catch (\RuntimeException $exception) {
                $io->error(sprintf('%s: %s', $film['id'], $exception->getMessage()));
                $failed = true;
            }
        }

        $changes = $this->counts['created'] + $this->counts['updated'] + $this->counts['moved'] + $this->counts['files'];
        $io->success(sprintf(
            '%s%d change(s): %d created, %d updated, %d moved, %d file field(s) (%d file(s) imported).',
            $this->dryRun ? 'Dry run, ' : '',
            $changes,
            $this->counts['created'],
            $this->counts['updated'],
            $this->counts['moved'],
            $this->counts['files'],
            $this->counts['imported'],
        ));

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * The film.json files below $directory, sorted by path, so a film placed
     * after another one (`after.film`) finds it in the same run when its id
     * sorts first. A film folder holds either one film.json or cut folders
     * with one each (short/film.json, long/film.json); when both exist during
     * a change-over, the cuts win and the folder's own manifest is skipped.
     *
     * @return list<string>
     */
    public static function manifests(string $directory): array
    {
        $base = rtrim($directory, '/');
        $cuts = glob($base . '/*/*/film.json') ?: [];
        $foldersWithCuts = array_unique(array_map(static fn(string $file): string => dirname($file, 2), $cuts));
        $films = array_values(array_filter(
            glob($base . '/*/film.json') ?: [],
            static fn(string $file): bool => !in_array(dirname($file), $foldersWithCuts, true),
        ));
        $files = [...$films, ...$cuts];
        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * The video element's options from film.json, with their defaults:
     * `variant` "default", `autoplay` true, `eyebrow` "Film", `caption` "".
     *
     * @param array<string, mixed> $film
     * @return array{variant: string, autoplay: bool, eyebrow: string, caption: string}
     */
    public static function videoOptionsOf(array $film): array
    {
        $variant = $film['variant'] ?? 'default';
        if (!is_string($variant) || !in_array($variant, self::VARIANTS, true)) {
            throw new \RuntimeException(sprintf('film.json "variant" must be one of %s.', implode(', ', self::VARIANTS)));
        }
        $autoplay = $film['autoplay'] ?? true;
        if (!is_bool($autoplay)) {
            throw new \RuntimeException('film.json "autoplay" must be true or false.');
        }
        $eyebrow = $film['eyebrow'] ?? 'Film';
        if (!is_string($eyebrow) || trim($eyebrow) === '') {
            throw new \RuntimeException('film.json "eyebrow" must be a non-empty text.');
        }
        $caption = $film['caption'] ?? '';
        if (!is_string($caption)) {
            throw new \RuntimeException('film.json "caption" must be a text.');
        }

        return ['variant' => $variant, 'autoplay' => $autoplay, 'eyebrow' => $eyebrow, 'caption' => $caption];
    }

    /** @param array<string, mixed> $film
     * @return list<string>
     */
    public static function captionFilesOf(array $film): array
    {
        $tracks = $film['captionTracks'] ?? [$film['vtt'] ?? null];
        if (!is_array($tracks) || $tracks === [] || count($tracks) > 10) {
            throw new \RuntimeException('film.json must name between one and ten caption tracks.');
        }
        $files = [];
        foreach ($tracks as $name) {
            if (!is_string($name) || basename($name) !== $name || !str_ends_with($name, '.vtt') || in_array($name, $files, true)) {
                throw new \RuntimeException('film.json caption tracks must be distinct WebVTT filenames.');
            }
            $files[] = $name;
        }
        return $files;
    }

    /**
     * Replaces every `{{page:<slug>}}` in $text with `t3://page?uid=<uid>`.
     * The slug may be written with or without its leading "/" (`{{page:}}`
     * is the home page); $pageUid returns the uid of the default-language
     * page with that slug, or null when there is none.
     *
     * @param callable(string): ?int $pageUid
     */
    public static function resolvePageLinks(string $text, callable $pageUid): string
    {
        return (string)preg_replace_callback(
            '/\{\{page:([^}]*)\}\}/',
            static function (array $matches) use ($pageUid): string {
                $slug = '/' . trim($matches[1], " /");
                $uid = $pageUid($slug);
                if ($uid === null) {
                    throw new \RuntimeException(sprintf('film.json links %s, but the Desiderio site has no page with the slug %s.', $matches[0], $slug));
                }

                return 't3://page?uid=' . $uid;
            },
            $text,
        );
    }

    /**
     * Where a film goes: film.json's `placements`, normalised, or its `slug`
     * after the intro when there are none. A placement follows at most one
     * anchor: an element's header (`after.header`) or another film's
     * transcript (`after.film`).
     *
     * @param array<string, mixed> $film
     * @return list<array{slug: string, afterHeader: ?string, afterFilm: ?string}>
     */
    public static function placementsOf(array $film): array
    {
        if (!array_key_exists('placements', $film)) {
            $slug = $film['slug'] ?? null;
            if (!is_string($slug) || !str_starts_with($slug, '/')) {
                throw new \RuntimeException('film.json has no slug.');
            }
            return [['slug' => $slug, 'afterHeader' => null, 'afterFilm' => null]];
        }
        $placements = $film['placements'];
        if (!is_array($placements) || $placements === [] || !array_is_list($placements)) {
            throw new \RuntimeException('film.json "placements" must be a non-empty list.');
        }
        $normalised = [];
        $seen = [];
        foreach ($placements as $index => $placement) {
            $slug = is_array($placement) ? ($placement['slug'] ?? null) : null;
            if (!is_string($slug) || !str_starts_with($slug, '/')) {
                throw new \RuntimeException(sprintf('Placement %d has no slug starting with "/".', $index + 1));
            }
            $afterHeader = null;
            $afterFilm = null;
            if (array_key_exists('after', $placement)) {
                $after = $placement['after'];
                if (!is_array($after) || count($after) !== 1 || !(array_key_exists('header', $after) || array_key_exists('film', $after))) {
                    throw new \RuntimeException(sprintf('Placement %d: "after" needs exactly one of "header" or "film".', $index + 1));
                }
                if (array_key_exists('header', $after)) {
                    $header = $after['header'];
                    if (!is_string($header) || trim($header) === '') {
                        throw new \RuntimeException(sprintf('Placement %d: "after" needs a non-empty "header".', $index + 1));
                    }
                    $afterHeader = $header;
                } else {
                    $other = $after['film'];
                    if (!is_string($other) || preg_match('/^[a-z0-9][a-z0-9-]*$/', $other) !== 1) {
                        throw new \RuntimeException(sprintf('Placement %d: "after.film" must be a film id.', $index + 1));
                    }
                    if ($other === ($film['id'] ?? null)) {
                        throw new \RuntimeException(sprintf('Placement %d: a film cannot follow itself.', $index + 1));
                    }
                    $afterFilm = $other;
                }
            }
            $key = $slug . "\n" . ($afterHeader ?? '') . "\n" . ($afterFilm ?? '');
            if (isset($seen[$key])) {
                throw new \RuntimeException(sprintf('Placement %d repeats placement %d.', $index + 1, $seen[$key]));
            }
            $seen[$key] = $index + 1;
            $normalised[] = ['slug' => $slug, 'afterHeader' => $afterHeader, 'afterFilm' => $afterFilm];
        }

        return $normalised;
    }

    /**
     * The marker keys of a film's placement (0-based): `<id>` and `<id>-transcript`
     * for the first, `<id>#2` and `<id>#2-transcript` for the second, and so on.
     *
     * @return array{video: string, transcript: string}
     */
    public static function markerKeys(string $id, int $placement): array
    {
        $key = $placement === 0 ? $id : $id . '#' . ($placement + 1);

        return ['video' => $key, 'transcript' => $key . '-transcript'];
    }

    /**
     * The rowDescription that identifies a seeded element.
     */
    public static function marker(string $key): string
    {
        return sprintf('Seeded by sitepackage:films:seed (%s). A reseed replaces it.', $key);
    }

    /**
     * @param array<string, mixed> $film
     */
    private function seed(array $film, string $directory): void
    {
        $id = (string)$film['id'];
        $placements = self::placementsOf($film);
        $options = self::videoOptionsOf($film);
        foreach ([$this->text($film, 'mp4'), $this->text($film, 'poster'), ...self::captionFilesOf($film)] as $name) {
            if (!is_file($directory . '/' . $name)) {
                throw new \RuntimeException(sprintf('film.json names %s, which is not in %s.', $name, $directory));
            }
        }
        foreach ($placements as $index => $placement) {
            $page = $this->pageBySlug($placement['slug']);
            if ($page === null) {
                throw new \RuntimeException(sprintf('No page %s below the Desiderio root %d.', $placement['slug'], $this->rootUid));
            }
            if ($this->picturesOnly) {
                $key = self::markerKeys($id, $index)['video'];
                $video = $this->byMarker($page, self::marker($key));
                if ($video === null) {
                    $this->output->writeln(sprintf('skip    %s: no published video to refresh.', $key));
                    continue;
                }
                $this->refreshPictureReference($video, 'video_file', $directory . '/' . $this->text($film, 'mp4'), $id);
                $this->refreshPictureReference($video, 'poster', $directory . '/' . $this->text($film, 'poster'), $id);
                continue;
            }
            $this->place($film, $options, $directory, $page, $placement, self::markerKeys($id, $index));
        }
    }

    /**
     * One placement: the video after its anchor, the transcript after the video.
     *
     * @param array<string, mixed> $film
     * @param array{variant: string, autoplay: bool, eyebrow: string, caption: string} $options
     * @param array{slug: string, afterHeader: ?string, afterFilm: ?string} $placement
     * @param array{video: string, transcript: string} $keys
     */
    private function place(array $film, array $options, string $directory, int $page, array $placement, array $keys): void
    {
        $id = (string)$film['id'];
        $pageUid = fn(string $slug): ?int => $this->pageBySlug($slug);

        $videoFields = [
            'CType' => 'desiderio_featurevideo',
            'colPos' => '0',
            'eyebrow' => $options['eyebrow'],
            'header' => $this->text($film, 'header'),
            'description' => self::resolvePageLinks($this->text($film, 'description'), $pageUid),
            'video_url' => '',
            'caption' => self::resolvePageLinks($options['caption'], $pageUid),
            self::VARIANT_FIELD => $options['variant'],
        ];
        $transcriptFields = [
            'CType' => 'desiderio_accordion',
            'colPos' => '0',
            'header' => self::TRANSCRIPT_HEADER,
            'allow_multiple' => '1',
        ];
        $item = ['title' => self::TRANSCRIPT_TITLE, 'open_by_default' => '0', 'content' => $this->transcript($film)];

        $video = $this->element($page, $keys['video'], $videoFields, [], $this->anchor($page, $placement));
        $transcript = $this->element($page, $keys['transcript'], $transcriptFields, ['desiderio_accordion_items' => $item], $video);
        if ($video === null || $transcript === null) {
            return;
        }

        $alt = $this->text($film, 'alt');
        $title = $this->text($film, 'title');
        $this->files($video, 'video_file', $directory . '/' . $this->text($film, 'mp4'), $id, ['alternative' => $alt, 'title' => $title, 'autoplay' => $options['autoplay'] ? 1 : 0]);
        $this->files($video, 'poster', $directory . '/' . $this->text($film, 'poster'), $id, ['alternative' => $alt, 'title' => $title]);
        $captionPaths = array_map(static fn(string $name): string => $directory . '/' . $name, self::captionFilesOf($film));
        $this->files($video, 'captions_file', $captionPaths, $id, []);
        $this->collection($transcript, 'desiderio_accordion_items', $item, $keys['video']);
        $this->order($page, $video, $transcript, $keys['video'], $placement);
    }

    /**
     * The element with the marker, brought to $fields; created after $after
     * (at the top without it) when it is missing, with its collection item.
     *
     * @param array<string, string> $fields
     * @param array<string, array<string, string>> $collections
     */
    private function element(int $page, string $key, array $fields, array $collections, ?int $after): ?int
    {
        $marker = self::marker($key);
        $fields['rowDescription'] = $marker;
        $uid = $this->byMarker($page, $marker);
        if ($uid !== null) {
            $this->update('tt_content', $uid, $fields, $key);
            return $uid;
        }

        $this->counts['created']++;
        $this->output->writeln(sprintf('create  tt_content %s on page %d', $key, $page));
        if ($this->dryRun) {
            return null;
        }
        $id = $this->newId();
        $dataMap = ['tt_content' => [$id => $fields + ['pid' => $after !== null ? -$after : $page, 'sys_language_uid' => 0]]];
        foreach ($collections as $field => $row) {
            $childId = $this->newId();
            $dataMap[$this->foreignTable($field)][$childId] = $row + ['pid' => $page, 'sys_language_uid' => 0];
            $dataMap['tt_content'][$id][$field] = $childId;
        }
        $handler = $this->process($dataMap);
        $uid = (int)($handler->substNEWwithIDs[$id] ?? 0);
        if ($uid <= 0) {
            throw new \RuntimeException(sprintf('DataHandler did not create %s.', $key));
        }

        return $uid;
    }

    /**
     * The accordion's one item: updated, created when missing; further items
     * (added in the backend) are deleted.
     *
     * @param array<string, string> $fields
     */
    private function collection(int $parent, string $field, array $fields, string $label): void
    {
        $table = $this->foreignTable($field);
        $children = $this->children($table, $parent);
        if ($children !== []) {
            $this->update($table, $children[0], $fields, $label . ' transcript');
        }
        if (count($children) === 1) {
            return;
        }
        $dataMap = [];
        $commandMap = [];
        if ($children === []) {
            $this->counts['created']++;
            $this->output->writeln(sprintf('create  %s %s', $table, $label));
            $id = $this->newId();
            $dataMap[$table][$id] = $fields + ['pid' => $this->pid($parent), 'sys_language_uid' => 0];
            $dataMap['tt_content'][$parent][$field] = $id;
        }
        foreach (array_slice($children, 1) as $surplus) {
            $this->counts['updated']++;
            $this->output->writeln(sprintf('delete  %s %d (%s)', $table, $surplus, $label));
            $commandMap[$table][$surplus]['delete'] = 1;
        }
        if (!$this->dryRun) {
            $this->process($dataMap, $commandMap);
        }
    }

    /**
     * Video, then transcript, right after the anchor: the element with the
     * placement's header, the other film's transcript, or the page's intro
     * (a hero or a header section as its first element), or at the top
     * without one.
     *
     * @param array{slug: string, afterHeader: ?string, afterFilm: ?string} $placement
     */
    private function order(int $page, int $video, int $transcript, string $label, array $placement): void
    {
        $anchor = $this->anchor($page, $placement, [$video, $transcript]);
        $wanted = [$video, $transcript];
        $all = $this->contentOf($page);
        $offset = $anchor === null ? 0 : (int)array_search($anchor, $all, true) + 1;
        if (array_slice($all, $offset, 2) === $wanted) {
            return;
        }
        $this->counts['moved']++;
        $this->output->writeln(sprintf('move    %s below %s', $label, $anchor === null ? 'the top of the page' : 'tt_content ' . $anchor));
        if ($this->dryRun) {
            return;
        }
        $this->process([], ['tt_content' => [$video => ['move' => $anchor === null ? $page : -$anchor]]]);
        $this->process([], ['tt_content' => [$transcript => ['move' => -$video]]]);
    }

    /**
     * The element a placement's video follows: the first default-language
     * element in colPos 0 whose header is `afterHeader`, the transcript of
     * the film `afterFilm` on this page, or the page's intro.
     *
     * @param array{slug: string, afterHeader: ?string, afterFilm: ?string} $placement
     * @param list<int> $without
     */
    private function anchor(int $page, array $placement, array $without = []): ?int
    {
        if ($placement['afterFilm'] !== null) {
            $other = $placement['afterFilm'];
            $transcript = $this->byMarker($page, self::marker(self::markerKeys($other, 0)['transcript']));
            if ($transcript === null || in_array($transcript, $without, true)) {
                throw new \RuntimeException(sprintf(
                    'Film %s is not on page %d (%s) yet: no element with its transcript marker. Seed %s first; this placement is retried on the next run.',
                    $other,
                    $page,
                    $placement['slug'],
                    $other,
                ));
            }

            return $transcript;
        }
        $afterHeader = $placement['afterHeader'];
        if ($afterHeader === null) {
            return $this->intro($page, $without);
        }
        $all = $this->contentOf($page, $without);
        if ($all !== []) {
            $queryBuilder = $this->queryBuilder('tt_content');
            $match = $queryBuilder->select('uid')->from('tt_content')->where(
                $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($all, Connection::PARAM_INT_ARRAY)),
                $queryBuilder->expr()->eq('header', $queryBuilder->createNamedParameter($afterHeader)),
            )->executeQuery()->fetchFirstColumn();
            $matching = array_map('intval', $match);
            foreach ($all as $uid) {
                if (in_array($uid, $matching, true)) {
                    return $uid;
                }
            }
        }
        throw new \RuntimeException(sprintf('Page %d has no element with the header "%s".', $page, $afterHeader));
    }

    /**
     * Replaces a file field's reference when the file or its texts differ.
     *
     * @param array{alternative?: string, title?: string, autoplay?: int} $texts
     * @param string|list<string> $path
     */
    private function files(int $uid, string $field, string|array $path, string $film, array $texts): void
    {
        $paths = is_array($path) ? $path : [$path];
        $hashes = array_map(static fn(string $file): string => (string)sha1_file($file), $paths);
        $existing = $this->references($uid, $field);
        if (array_column($existing, 'sha1') === $hashes
            && array_all($existing, static fn(array $reference): bool =>
                $reference['alternative'] === ($texts['alternative'] ?? '')
                && $reference['title'] === ($texts['title'] ?? '')
                && (int)$reference['autoplay'] === ($texts['autoplay'] ?? 0))
        ) {
            return;
        }
        $this->counts['files']++;
        $this->output->writeln(sprintf('files   tt_content %d.%s: %s', $uid, $field, implode(', ', array_map('basename', $paths))));
        if ($this->dryRun) {
            return;
        }
        $references = [];
        foreach ($paths as $index => $filePath) {
            $file = $this->import($filePath, $film, $hashes[$index]);
            $references[$this->newId()] = ['uid_local' => $file->getUid(), 'uid_foreign' => $uid, 'tablenames' => 'tt_content', 'fieldname' => $field, 'pid' => $this->pid($uid), 'sys_language_uid' => 0, 'sorting_foreign' => $index + 1] + $texts;
        }
        $commandMap = [];
        foreach ($existing as $old) {
            $commandMap['sys_file_reference'][$old['uid']]['delete'] = 1;
        }
        $this->process(['sys_file_reference' => $references, 'tt_content' => [$uid => [$field => implode(',', array_keys($references))]]], $commandMap);
    }

    /**
     * Keep reference UIDs, translated metadata and caption tracks intact when
     * replacing only a film's picture. DataHandler maintains history and the
     * reference index for the source and its existing translations.
     */
    private function refreshPictureReference(int $video, string $field, string $path, string $film): void
    {
        $source = $this->references($video, $field);
        if (count($source) !== 1) {
            throw new \RuntimeException(sprintf('Expected one existing reference for %d.%s.', $video, $field));
        }
        $query = $this->queryBuilder('sys_file_reference');
        $rows = $query->select('r.uid', 'f.sha1')->from('sys_file_reference', 'r')
            ->innerJoin('r', 'sys_file', 'f', 'f.uid = r.uid_local')
            ->innerJoin('r', 'tt_content', 'c', 'c.uid = r.uid_foreign')
            ->where(
                $query->expr()->or(
                    $query->expr()->eq('r.uid', $query->createNamedParameter($source[0]['uid'], Connection::PARAM_INT)),
                    $query->expr()->eq('r.l10n_parent', $query->createNamedParameter($source[0]['uid'], Connection::PARAM_INT)),
                ),
                $query->expr()->or(
                    $query->expr()->eq('c.uid', $query->createNamedParameter($video, Connection::PARAM_INT)),
                    $query->expr()->eq('c.l18n_parent', $query->createNamedParameter($video, Connection::PARAM_INT)),
                ),
                $query->expr()->eq('r.tablenames', $query->createNamedParameter('tt_content')),
                $query->expr()->eq('r.fieldname', $query->createNamedParameter($field)),
                'r.deleted = 0', 'r.t3ver_wsid = 0', 'c.deleted = 0', 'c.t3ver_wsid = 0',
            )->executeQuery()->fetchAllAssociative();
        $sha1 = (string)sha1_file($path);
        $changed = array_filter($rows, static fn(array $row): bool => $row['sha1'] !== $sha1);
        if ($changed === []) {
            return;
        }
        $this->counts['files']++;
        $this->output->writeln(sprintf('picture %d.%s: %d reference(s) -> %s', $video, $field, count($changed), basename($path)));
        if ($this->dryRun) {
            return;
        }
        $file = $this->import($path, $film, $sha1);
        $updates = [];
        foreach ($changed as $row) {
            $updates[(int)$row['uid']] = ['uid_local' => $file->getUid()];
        }
        $this->process(['sys_file_reference' => $updates]);
    }

    private function import(string $path, string $film, string $sha1): File
    {
        $storage = $this->storageRepository->getDefaultStorage();
        if (!$storage instanceof ResourceStorage) {
            throw new \RuntimeException('No default file storage.');
        }
        $folderPath = '/' . self::FOLDER . '/' . $film . '/';
        $folder = $storage->hasFolder($folderPath) ? $storage->getFolder($folderPath) : $storage->createFolder(self::FOLDER . '/' . $film);
        foreach ($folder->getFiles() as $candidate) {
            if ($candidate->getSha1() === $sha1) {
                return $candidate;
            }
        }
        // A copy, so the fixture in the package is never moved.
        $temporary = GeneralUtility::tempnam('site-film-');
        try {
            copy($path, $temporary);
            $file = $storage->addFile($temporary, $folder, basename($path), DuplicationBehavior::RENAME, false);
        } finally {
            GeneralUtility::unlink_tempfile($temporary);
        }
        $this->counts['imported']++;

        return $file;
    }

    /**
     * @param array<string, mixed> $film
     */
    private function transcript(array $film): string
    {
        $lines = is_array($film['transcript'] ?? null) ? $film['transcript'] : [];
        $html = '';
        foreach ($lines as $line) {
            if (is_array($line) && is_string($line['at'] ?? null) && is_string($line['text'] ?? null)) {
                $html .= sprintf('<p><strong>%s</strong> %s</p>', htmlspecialchars($line['at']), htmlspecialchars($line['text'], ENT_NOQUOTES));
            }
        }

        return $html;
    }

    // ------------------------------------------------------------- records

    /**
     * @param array<string, string> $fields
     */
    private function update(string $table, int $uid, array $fields, string $label): void
    {
        $row = $this->queryBuilder($table)->select('*')->from($table)->where('uid = ' . $uid)->executeQuery()->fetchAssociative() ?: [];
        $changed = [];
        foreach ($fields as $name => $value) {
            if (!array_key_exists($name, $row)) {
                throw new \RuntimeException(sprintf('%s has no column %s.', $table, $name));
            }
            $current = str_replace("\r\n", "\n", (string)$row[$name]);
            // Elements seeded before the layout select existed have it empty, which renders as "default".
            if ($current === '' && $name === self::VARIANT_FIELD && $value === 'default') {
                continue;
            }
            if ($current !== $value) {
                $changed[$name] = $value;
            }
        }
        if ($changed === []) {
            return;
        }
        $this->counts['updated']++;
        $this->output->writeln(sprintf('update  %s %d (%s): %s', $table, $uid, $label, implode(', ', array_keys($changed))));
        if (!$this->dryRun) {
            $this->process([$table => [$uid => $changed]]);
        }
    }

    private function byMarker(int $page, string $marker): ?int
    {
        $queryBuilder = $this->queryBuilder('tt_content');
        $uid = $queryBuilder->select('uid')->from('tt_content')->where(
            $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($page, Connection::PARAM_INT)),
            $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            $queryBuilder->expr()->eq('rowDescription', $queryBuilder->createNamedParameter($marker)),
            ...$this->live($queryBuilder),
        )->orderBy('uid')->setMaxResults(1)->executeQuery()->fetchOne();

        return $uid === false ? null : (int)$uid;
    }

    /**
     * Uids of the page's default-language elements in colPos 0, in order.
     *
     * @param list<int> $without
     * @return list<int>
     */
    private function contentOf(int $page, array $without = []): array
    {
        $queryBuilder = $this->queryBuilder('tt_content');
        $uids = $queryBuilder->select('uid')->from('tt_content')->where(
            $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($page, Connection::PARAM_INT)),
            $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            $queryBuilder->expr()->eq('colPos', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            ...$this->live($queryBuilder),
        )->orderBy('sorting')->addOrderBy('uid')->executeQuery()->fetchFirstColumn();

        return array_values(array_diff(array_map('intval', $uids), $without));
    }

    /**
     * The page's first element when it is a hero or a header section.
     *
     * @param list<int> $without
     */
    private function intro(int $page, array $without = []): ?int
    {
        $first = $this->contentOf($page, $without)[0] ?? null;
        if ($first === null) {
            return null;
        }
        $type = (string)$this->queryBuilder('tt_content')->select('CType')->from('tt_content')->where('uid = ' . $first)->executeQuery()->fetchOne();

        return preg_match('/^desiderio_(hero|headersection)/', $type) === 1 ? $first : null;
    }

    /**
     * @return list<int>
     */
    private function children(string $table, int $parent): array
    {
        $queryBuilder = $this->queryBuilder($table);
        $uids = $queryBuilder->select('uid')->from($table)->where(
            $queryBuilder->expr()->eq('foreign_table_parent_uid', $queryBuilder->createNamedParameter($parent, Connection::PARAM_INT)),
            $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            ...$this->live($queryBuilder),
        )->orderBy('sorting')->addOrderBy('uid')->executeQuery()->fetchFirstColumn();

        return array_map('intval', $uids);
    }

    /**
     * @return list<array{uid: int, sha1: string, alternative: string, title: string, autoplay: int}>
     */
    private function references(int $uid, string $field): array
    {
        $queryBuilder = $this->queryBuilder('sys_file_reference');
        $rows = $queryBuilder
            ->select('r.uid', 'r.alternative', 'r.title', 'r.autoplay', 'f.sha1')
            ->from('sys_file_reference', 'r')
            ->leftJoin('r', 'sys_file', 'f', $queryBuilder->expr()->eq('f.uid', $queryBuilder->quoteIdentifier('r.uid_local')))
            ->where(
                $queryBuilder->expr()->eq('r.uid_foreign', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('r.tablenames', $queryBuilder->createNamedParameter('tt_content')),
                $queryBuilder->expr()->eq('r.fieldname', $queryBuilder->createNamedParameter($field)),
                $queryBuilder->expr()->eq('r.sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('r.deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('r.t3ver_wsid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->orderBy('r.sorting_foreign')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(static fn(array $row): array => [
            'uid' => (int)$row['uid'],
            'sha1' => (string)$row['sha1'],
            'alternative' => (string)$row['alternative'],
            'title' => (string)$row['title'],
            'autoplay' => (int)$row['autoplay'],
        ], $rows);
    }

    private function pageBySlug(string $slug): ?int
    {
        $queryBuilder = $this->queryBuilder('pages');
        $candidates = $queryBuilder->select('uid')->from('pages')->where(
            $queryBuilder->expr()->eq('slug', $queryBuilder->createNamedParameter($slug)),
            $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            ...$this->live($queryBuilder),
        )->executeQuery()->fetchFirstColumn();
        foreach ($candidates as $candidate) {
            $uid = (int)$candidate;
            $seen = [];
            for ($cursor = $uid; $cursor > 0 && !isset($seen[$cursor]); $cursor = $this->pagePid($cursor)) {
                if ($cursor === $this->rootUid) {
                    return $uid;
                }
                $seen[$cursor] = true;
            }
        }

        return null;
    }

    private function pagePid(int $uid): int
    {
        return (int)$this->queryBuilder('pages')->select('pid')->from('pages')->where('uid = ' . $uid)->executeQuery()->fetchOne();
    }

    private function pid(int $uid): int
    {
        return (int)$this->queryBuilder('tt_content')->select('pid')->from('tt_content')->where('uid = ' . $uid)->executeQuery()->fetchOne();
    }

    private function foreignTable(string $field): string
    {
        $table = $GLOBALS['TCA']['tt_content']['columns'][$field]['config']['foreign_table'] ?? null;
        if (!is_string($table) || $table === '') {
            throw new \RuntimeException(sprintf('tt_content.%s has no foreign_table.', $field));
        }

        return $table;
    }

    private function queryBuilder(string $table): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder;
    }

    /**
     * Live, not deleted rows; hidden ones count (an editor may have hidden one).
     *
     * @return list<string>
     */
    private function live(QueryBuilder $queryBuilder): array
    {
        return [
            (string)$queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            (string)$queryBuilder->expr()->eq('t3ver_wsid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
        ];
    }

    /**
     * @param array<string, mixed> $film
     */
    private function text(array $film, string $key): string
    {
        $value = $film[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \RuntimeException(sprintf('film.json has no %s.', $key));
        }

        return $value;
    }

    private function newId(): string
    {
        // No "_" in NEW ids: DataHandler's remap stack reads <table>_<uid> out of them.
        return 'NEW' . substr(hash('sha256', spl_object_id($this) . '|' . (++$this->newCounter) . '|' . microtime()), 0, 24);
    }

    /**
     * @param array<string, array<int|string, array<string, mixed>>> $dataMap
     * @param array<string, array<int|string, array<string, mixed>>> $commandMap
     */
    private function process(array $dataMap, array $commandMap = []): DataHandler
    {
        $handler = GeneralUtility::makeInstance(DataHandler::class);
        $handler->isImporting = true;
        // Rich text is stored as written, so the next run compares equal.
        $handler->dontProcessTransformations = true;
        $handler->start($dataMap, $commandMap);
        $handler->process_datamap();
        $handler->process_cmdmap();
        if ($handler->errorLog !== []) {
            throw new \RuntimeException('DataHandler refused a write: ' . implode(' | ', array_map(static fn(mixed $error): string => is_scalar($error) ? (string)$error : '?', $handler->errorLog)));
        }

        return $handler;
    }
}
