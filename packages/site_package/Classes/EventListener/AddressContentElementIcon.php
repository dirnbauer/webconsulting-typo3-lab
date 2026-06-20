<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Configuration\Event\AfterTcaCompilationEvent;

/**
 * Swaps the third-party tt_address "Addresses" content element (CType
 * ttaddress_listview) over to a custom v14 line-art icon, so it matches the rest
 * of the New Content Element Wizard. Runs on AfterTcaCompilationEvent (after all
 * extensions' TCA is built) and overrides only the CType item icon +
 * typeicon_classes — no edit to vendor/friendsoftypo3/tt-address, which a
 * composer update would revert.
 */
final class AddressContentElementIcon
{
    private const CTYPE = 'ttaddress_listview';
    private const ICON = 'site-ce-address';

    #[AsEventListener('site-package/address-content-element-icon')]
    public function __invoke(AfterTcaCompilationEvent $event): void
    {
        $tca = $event->getTca();

        $ttContent = $tca['tt_content'] ?? null;
        $columns = is_array($ttContent) ? ($ttContent['columns'] ?? null) : null;
        $cTypeField = is_array($columns) ? ($columns['CType'] ?? null) : null;
        $config = is_array($cTypeField) ? ($cTypeField['config'] ?? null) : null;
        $items = is_array($config) ? ($config['items'] ?? null) : null;
        if (!is_array($ttContent) || !is_array($columns) || !is_array($cTypeField)
            || !is_array($config) || !is_array($items)
        ) {
            return;
        }

        $changed = false;
        foreach ($items as $key => $item) {
            if (is_array($item) && ($item['value'] ?? null) === self::CTYPE) {
                $item['icon'] = self::ICON;
                $items[$key] = $item;
                $changed = true;
            }
        }

        if (!$changed) {
            return;
        }

        $ctrl = $ttContent['ctrl'] ?? [];
        $ctrl = is_array($ctrl) ? $ctrl : [];
        $typeicons = $ctrl['typeicon_classes'] ?? [];
        $typeicons = is_array($typeicons) ? $typeicons : [];
        $typeicons[self::CTYPE] = self::ICON;

        $config['items'] = $items;
        $cTypeField['config'] = $config;
        $columns['CType'] = $cTypeField;
        $ttContent['columns'] = $columns;
        $ctrl['typeicon_classes'] = $typeicons;
        $ttContent['ctrl'] = $ctrl;
        $tca['tt_content'] = $ttContent;

        $event->setTca($tca);
    }
}
