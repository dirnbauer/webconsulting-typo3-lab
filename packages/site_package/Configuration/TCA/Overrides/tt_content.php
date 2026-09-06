<?php

declare(strict_types=1);

defined('TYPO3') or die();

// tt_address is a Composer dependency, so its CType is registered first.
foreach ($GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'] as $index => $item) {
    if (($item['value'] ?? '') === 'ttaddress_listview') {
        $GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'][$index]['icon'] = 'site-ce-address';
        $GLOBALS['TCA']['tt_content']['ctrl']['typeicon_classes']['ttaddress_listview'] = 'site-ce-address';
    }
}
