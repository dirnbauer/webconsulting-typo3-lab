<?php
// Project-level functional-test bootstrap.
//
// Defines the TYPO3 constant before any vendor extension's ext_emconf.php is
// included by typo3/testing-framework's ComposerPackageManager. Two webconsulting/*
// extensions in this project ship an ext_emconf.php with `defined('TYPO3') or die();`
// at the top; without this guard the bootstrap die()s silently inside the included
// metadata file.
if (!defined('TYPO3')) {
    define('TYPO3', 'BE');
}

// Vendor cms-* extensions don't expose their Tests/ trees via composer autoload,
// so a class like TYPO3\CMS\Core\Tests\Functional\SiteHandling\SiteBasedTestTrait
// is not findable by the default PSR-4 loader. Register a small additional
// PSR-4 loader that maps TYPO3\CMS\<Ext>\Tests\ to vendor/typo3/cms-<ext>/Tests/.
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'TYPO3\\CMS\\')) {
        return;
    }
    $remainder = substr($class, strlen('TYPO3\\CMS\\'));
    $parts = explode('\\', $remainder);
    if (count($parts) < 3 || $parts[1] !== 'Tests') {
        return;
    }
    $extPart = $parts[0];
    $extKey = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $extPart));
    $base = __DIR__ . '/../vendor/typo3/cms-' . $extKey . '/Tests/';
    $relative = implode('/', array_slice($parts, 2)) . '.php';
    $file = $base . $relative;
    if (is_file($file)) {
        require $file;
    }
});

(static function () {
    $testbase = new \TYPO3\TestingFramework\Core\Testbase();
    $testbase->defineOriginalRootPath();
    $testbase->createDirectory(ORIGINAL_ROOT . 'typo3temp/var/tests');
    $testbase->createDirectory(ORIGINAL_ROOT . 'typo3temp/var/transient');
})();
