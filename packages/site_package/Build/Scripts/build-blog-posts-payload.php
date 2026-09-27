<?php

declare(strict_types=1);

/**
 * Turns a file of blog post definitions into a content payload for
 * sitepackage:content:apply.
 *
 * The definitions are what an editor would write (title, teaser, date,
 * categories, tags, a featured image and sections of text with optional
 * screenshots); the payload is what DataHandler needs: the author, the
 * categories and tags a post uses, the post pages and their content
 * elements, each as a `create` record with a `match`, so a second run finds
 * everything and changes nothing. Posts and content elements refer to each
 * other with `@<key>` references.
 *
 *   php packages/site_package/Build/Scripts/build-blog-posts-payload.php \
 *     --definitions=packages/site_package/Build/Data/v14-blog/extension-posts.json \
 *     --out=packages/site_package/Resources/Private/Data/Content/v14-blog/extension-posts.payload.json \
 *     --site=v14-blog --root=69 --folder=70 --language=en \
 *     --layout=pagets__DesiderioBlogModern --author="Webconsulting TYPO3 Team"
 *
 * An image is either `file`, a file name from EXT:desiderio's live
 * screenshots (Resources/Public/Styleguide/Backend or /Frontend), or
 * `source`, any EXT: path; the payload imports it into --image-folder by
 * name. Content elements are listed last
 * first: DataHandler puts each new element at the top of its page, so the
 * page ends up in the order of the definitions.
 */

$options = getopt('', [
    'definitions:', 'out:', 'site:', 'root:', 'folder:', 'language::', 'layout:', 'author:',
    'author-email::', 'image-folder::', 'timezone::',
]);
foreach (['definitions', 'out', 'site', 'root', 'folder', 'layout', 'author'] as $required) {
    if (!is_string($options[$required] ?? null) || $options[$required] === '') {
        fwrite(STDERR, "Missing --{$required}\n");
        exit(2);
    }
}
/** @var array<string, string> $options */
$root = (int)$options['root'];
$folder = (int)$options['folder'];
$language = $options['language'] ?? 'en';
$layout = $options['layout'];
$imageFolder = $options['image-folder'] ?? '1:/blog/' . $options['site'] . '/';
$timezone = new DateTimeZone($options['timezone'] ?? 'Europe/Vienna');
$projectRoot = dirname(__DIR__, 4);
$screenshots = $projectRoot . '/packages/desiderio/Resources/Public/Styleguide/';

$definitions = json_decode((string)file_get_contents($options['definitions']), true, 512, JSON_THROW_ON_ERROR);
if (!is_array($definitions) || !is_array($definitions['posts'] ?? null)) {
    fwrite(STDERR, "No posts in {$options['definitions']}\n");
    exit(1);
}

$slugify = static function (string $value): string {
    $value = strtolower(trim((string)preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $value) ?: $value), '-'));
    return $value === '' ? 'item' : $value;
};
$keyOf = static fn (string $kind, string $name): string => 'NEW_blog_' . $kind . '_' . str_replace('-', '_', $slugify($name));
$image = static function (array $picture) use ($screenshots): string {
    // An explicit source (EXT:…) wins; a bare file name is one of desiderio's screenshots.
    if (is_string($picture['source'] ?? null) && $picture['source'] !== '') {
        return $picture['source'];
    }
    $file = (string)($picture['file'] ?? '');
    foreach (['Backend', 'Frontend'] as $group) {
        if (is_file($screenshots . $group . '/' . $file)) {
            return 'EXT:desiderio/Resources/Public/Styleguide/' . $group . '/' . $file;
        }
    }
    throw new RuntimeException('Screenshot not found: ' . $file);
};

$records = [];

// The author every post is filed under.
$authorKey = $keyOf('author', $options['author']);
$records[] = [
    'table' => 'tx_blog_domain_model_author',
    'action' => 'create',
    'key' => $authorKey,
    'pid' => $folder,
    'match' => ['pid' => $folder, 'name' => $options['author'], 'sys_language_uid' => 0],
    'set' => ['name' => $options['author']],
];

// Categories and tags, each once, in the order the posts first use them.
$categories = [];
$tags = [];
foreach ($definitions['posts'] as $post) {
    foreach ($post['categories'] as $title) {
        $categories[$title] ??= $keyOf('category', $title);
    }
    foreach ($post['tags'] as $title) {
        $tags[$title] ??= $keyOf('tag', $title);
    }
}
foreach ($categories as $title => $key) {
    $records[] = [
        'table' => 'sys_category',
        'action' => 'create',
        'key' => $key,
        'pid' => $folder,
        'match' => ['pid' => $folder, 'title' => $title, 'sys_language_uid' => 0],
        // EXT:blog only lists categories of its own record type.
        'set' => ['title' => $title, 'record_type' => '100', 'slug' => $slugify($title)]
            + (isset($definitions['categoryDescriptions'][$title]) ? ['description' => $definitions['categoryDescriptions'][$title]] : []),
    ];
}
foreach ($tags as $title => $key) {
    $records[] = [
        'table' => 'tx_blog_domain_model_tag',
        'action' => 'create',
        'key' => $key,
        'pid' => $folder,
        'match' => ['pid' => $folder, 'title' => $title, 'sys_language_uid' => 0],
        'set' => ['title' => $title, 'slug' => $slugify($title)],
    ];
}

foreach ($definitions['posts'] as $post) {
    $postKey = $keyOf('post', $post['key']);
    $publishDate = (new DateTimeImmutable($post['date'], $timezone))->getTimestamp();
    $set = [
        'doktype' => 137,
        'title' => $post['title'],
        'slug' => $post['slug'],
        'abstract' => $post['abstract'],
        'description' => $post['description'],
        'og_title' => $post['title'],
        'og_description' => $post['description'],
        'publish_date' => $publishDate,
        'backend_layout' => $layout,
        'backend_layout_next_level' => $layout,
        'comments_active' => 1,
        // TYPO3 creates pages hidden; a published post is visible.
        'hidden' => 0,
        'author' => $options['author'],
        'categories' => implode(',', array_map(static fn (string $title): string => '@' . $categories[$title], $post['categories'])),
        'tags' => implode(',', array_map(static fn (string $title): string => '@' . $tags[$title], $post['tags'])),
        'authors' => '@' . $authorKey,
        'sys_language_uid' => 0,
    ];
    if (($post['subtitle'] ?? '') !== '') {
        $set['subtitle'] = $post['subtitle'];
    }
    if (($options['author-email'] ?? '') !== '') {
        $set['author_email'] = $options['author-email'];
    }
    $records[] = [
        'table' => 'pages',
        'action' => 'create',
        'key' => $postKey,
        'pid' => $folder,
        'match' => ['pid' => $folder, 'slug' => $post['slug'], 'sys_language_uid' => 0],
        'set' => $set,
        'files' => [
            'featured_image' => [[
                'source' => $image($post['featuredImage']),
                'folder' => $imageFolder,
                'alternative' => $post['featuredImage']['alternative'],
            ]],
        ],
        '_context' => ['post' => $post['key']],
    ];

    // Last section first: each new element lands at the top of the page.
    $sections = $post['content'];
    for ($index = count($sections) - 1; $index >= 0; $index--) {
        $section = $sections[$index];
        $hasImage = is_array($section['image'] ?? null);
        $record = [
            'table' => 'tt_content',
            'action' => 'create',
            'key' => $keyOf('content', $post['key'] . '-' . ($index + 1)),
            'pid' => '@' . $postKey,
            'match' => ['pid' => '@' . $postKey, 'colPos' => 0, 'header' => $section['header'], 'sys_language_uid' => 0],
            'set' => [
                'CType' => $hasImage ? 'textmedia' : 'text',
                'colPos' => 0,
                'header' => $section['header'],
                'bodytext' => $section['body'],
                'sys_language_uid' => 0,
            ],
            '_context' => ['post' => $post['key'], 'section' => $index + 1],
        ];
        if ($hasImage) {
            // Below the text, full width, and it opens in the lightbox.
            $record['set']['imageorient'] = 8;
            $record['set']['image_zoom'] = 1;
            $record['files'] = [
                'assets' => [[
                    'source' => $image($section['image']),
                    'folder' => $imageFolder,
                    'alternative' => $section['image']['alternative'],
                    'description' => $section['image']['caption'] ?? '',
                ]],
            ];
        }
        $records[] = $record;
    }
}

$payload = [
    'version' => 1,
    'site' => $options['site'],
    'root' => $root,
    'language' => 0,
    'languageCode' => $language,
    'generated' => (new DateTimeImmutable('now', $timezone))->format(DATE_ATOM),
    '_note' => 'Generated by packages/site_package/Build/Scripts/build-blog-posts-payload.php from '
        . str_replace($projectRoot . '/', '', realpath($options['definitions']) ?: $options['definitions'])
        . '. Edit the definitions and generate again rather than editing this file.',
    'records' => $records,
];

file_put_contents(
    $options['out'],
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n"
);
fwrite(STDOUT, sprintf(
    "Wrote %s: %d posts, %d categories, %d tags, %d records.\n",
    $options['out'],
    count($definitions['posts']),
    count($categories),
    count($tags),
    count($records)
));
