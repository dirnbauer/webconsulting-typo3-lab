<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Configuration;

use PHPUnit\Framework\TestCase;

final class AddressIconTest extends TestCase
{
    public function testOverridePreservesPluginMetadataAndUnrelatedContentTypes(): void
    {
        if (!defined('TYPO3')) {
            define('TYPO3', true);
        }
        $original = $GLOBALS['TCA'] ?? null;
        $tca = ['tt_content' => [
            'columns' => ['CType' => ['config' => ['items' => [
                ['label' => 'Text', 'value' => 'text', 'icon' => 'content-text'],
                ['label' => 'Addresses', 'value' => 'ttaddress_listview', 'icon' => 'old', 'group' => 'plugins'],
            ]]]],
            'ctrl' => ['typeicon_classes' => ['text' => 'content-text', 'ttaddress_listview' => 'old']],
        ]];

        try {
            $GLOBALS['TCA'] = $tca;
            require __DIR__ . '/../../../Configuration/TCA/Overrides/tt_content.php';
            $tca['tt_content']['columns']['CType']['config']['items'][1]['icon'] = 'site-ce-address';
            $tca['tt_content']['ctrl']['typeicon_classes']['ttaddress_listview'] = 'site-ce-address';
            self::assertSame($tca, $GLOBALS['TCA']);
        } finally {
            if ($original === null) {
                unset($GLOBALS['TCA']);
            } else {
                $GLOBALS['TCA'] = $original;
            }
        }
    }
}
