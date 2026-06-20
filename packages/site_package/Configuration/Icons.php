<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    // Custom v14 line-art icon used to override the third-party tt_address
    // "Addresses" content element icon (see EventListener\AddressContentElementIcon),
    // so it matches the rest of the New Content Element Wizard. Avoids editing
    // vendor/friendsoftypo3/tt-address (which a composer update would overwrite).
    'site-ce-address' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:site_package/Resources/Public/Icons/ce-address.svg',
    ],
];
