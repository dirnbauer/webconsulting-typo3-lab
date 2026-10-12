<?php

declare(strict_types=1);

/**
 * Build guarded page-copy updates from the ordinary Powermail demo guide.
 *
 * php packages/site_package/Build/Scripts/build-powermail-explanations.php \
 *   var/powermail-check/2026-10-12-before-localization.json
 *
 * The input is a read-only snapshot of the six forms' tt_content rows.
 * Apply the resulting payload with sitepackage:content:apply after reviewing
 * the matching browser tests. No database connection is opened here.
 */

$root = dirname(__DIR__, 4);
$directory = $root . '/packages/site_package/Resources/Private/Data/Content/powermail';
$snapshotPath = $argv[1] ?? '';
if ($snapshotPath === '' || !is_file($snapshotPath)) {
    throw new InvalidArgumentException('Pass the path to the scoped record snapshot.');
}
$snapshot = json_decode((string)file_get_contents($snapshotPath), true, flags: JSON_THROW_ON_ERROR);
$guide = json_decode((string)file_get_contents($directory . '/explanations.json'), true, flags: JSON_THROW_ON_ERROR);
$rows = $snapshot['tt_content'];
$records = [];
$html = [];
$languages = ['en' => 0, 'de' => 1, 'zh' => 2, 'hu' => 3];
$checked = [
    'en' => 'Checked 12 October 2026: the described validation and a test submission were verified with English examples.',
    'de' => 'Geprüft am 12. Oktober 2026: Die beschriebene Prüfung und eine Testübermittlung wurden mit englischen Beispielen bestätigt.',
    'zh' => '检查日期：2026 年 10 月 12 日。已使用英文示例验证所述验证行为和测试提交。',
    'hu' => 'Ellenőrizve: 2026. október 12. Angol példákkal igazoltuk a leírt ellenőrzéseket és egy tesztbeküldést.',
];
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

foreach ($guide['forms'] as $slug => $form) {
    $marker = 'Powermail lab guide: ' . $slug;
    foreach ($languages as $code => $language) {
        $copy = $form[$code];
        $common = $guide['common'][$code];
        $body = '<p>' . $escape($copy['intro']) . '</p>';
        if (isset($copy['choices'])) {
            $body .= '<table><caption>' . $escape($common['choicesCaption']) . '</caption><thead><tr>';
            foreach ($common['choicesColumns'] as $label) {
                $body .= '<th scope="col">' . $escape($label) . '</th>';
            }
            $body .= '</tr></thead><tbody>';
            foreach ($copy['choices'] as [$label, $choices]) {
                $body .= '<tr><th scope="row">' . $escape($label) . '</th><td>' . $escape($choices) . '</td></tr>';
            }
            $body .= '</tbody></table>';
        }
        $body .= '<table><caption>' . $escape($common['caption']) . '</caption><thead><tr>';
        foreach ($common['columns'] as $label) {
            $body .= '<th scope="col">' . $escape($label) . '</th>';
        }
        $body .= '</tr></thead><tbody>';
        foreach ($copy['rows'] as $cells) {
            if (count($cells) !== 3) {
                throw new RuntimeException("Expected three cells in $slug/$code.");
            }
            $body .= '<tr><th scope="row">' . $escape($cells[0]) . '</th><td>' . $escape($cells[1]) . '</td><td>' . $escape($cells[2]) . '</td></tr>';
        }
        $body .= '</tbody></table><p>' . $escape($common['note']) . '</p>';
        if ($form['verification'] === 'passed') {
            $body .= '<p><strong>' . $escape($checked[$code]) . '</strong></p>';
        }
        $html[$slug][$code] = $body;
        $key = 'NEW_powermail_guide_' . str_replace('-', '_', $slug) . '_' . $code;
        $sourceKey = 'NEW_powermail_guide_' . str_replace('-', '_', $slug) . '_en';
        $existingGuides = array_values(array_filter($rows, static fn (array $row): bool =>
            (int)$row['pid'] === $form['page'] && (int)$row['sys_language_uid'] === $language && ($row['rowDescription'] ?? '') === $marker
        ));
        if (count($existingGuides) > 1) {
            throw new RuntimeException("Duplicate guide records for $slug/$code.");
        }
        $values = [
            'CType' => 'text',
            'header' => $common['header'],
            'header_layout' => 100,
            'bodytext' => $body,
            'rowDescription' => $marker,
            'colPos' => 0,
            // DataHandler assigns its own sorting to translated records on creation.
            'sorting' => (int)($existingGuides[0]['sorting'] ?? 256),
            'sys_language_uid' => $language,
        ];
        if ($language > 0) {
            $values['l18n_parent'] = '@' . $sourceKey;
            $values['l10n_source'] = '@' . $sourceKey;
        }
        $records[] = [
            'action' => 'create',
            'table' => 'tt_content',
            'key' => $key,
            'pid' => $form['page'],
            'match' => ['pid' => $form['page'], 'rowDescription' => $marker, 'sys_language_uid' => $language],
            'set' => $values,
            '_context' => ['page' => $form['page'], 'slug' => $slug, 'language' => $code],
        ];

        $thanks = array_values(array_filter($rows, static fn (array $row): bool =>
            (int)$row['pid'] === $form['thanksPage'] && (int)$row['sys_language_uid'] === $language && $row['CType'] === 'text'
        ));
        if (count($thanks) !== 1) {
            throw new RuntimeException("Expected one thank-you record for $slug/$code; found " . count($thanks));
        }
        $target = $thanks[0];
        $thankBody = '<p>' . $escape($common['thanks']) . '</p><p>' . $escape($copy['thanksExtra']) . '</p>';
        $thankBody .= '<p><a href="t3://page?uid=' . $form['page'] . '">' . $escape($common['back']) . '</a></p>';
        $records[] = [
            'table' => 'tt_content',
            'uid' => (int)$target['uid'],
            'expect' => ['pid' => $form['thanksPage'], 'sys_language_uid' => $language, 'CType' => 'text', 'bodytext' => $target['bodytext']],
            'set' => ['bodytext' => $thankBody],
            '_context' => ['page' => $form['thanksPage'], 'slug' => $slug . '/thank-you', 'language' => $code],
        ];
    }
}

$payload = ['version' => 1, 'site' => 'desiderio', 'root' => 505, 'records' => $records];
$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;
file_put_contents($directory . '/explanations.payload.json', json_encode($payload, $flags) . "\n");
file_put_contents($directory . '/explanations.html.json', json_encode($html, $flags) . "\n");
printf("Prepared %d records: 24 guide elements and 24 guarded thank-you updates.\n", count($records));
