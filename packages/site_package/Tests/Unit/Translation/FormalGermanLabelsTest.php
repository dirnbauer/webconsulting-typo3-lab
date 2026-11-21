<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Translation;

use Composer\InstalledVersions;
use PHPUnit\Framework\TestCase;
use Webconsulting\SitePackage\ContentAudit\Canon;
use Webconsulting\SitePackage\Translation\FormalGermanLabels;

/**
 * Guard for the owner rule "German copy uses Sie" in the backend labels of the
 * Netresearch AI stack: every German label these packages ship, with the
 * lab's overrides applied, must not address the reader informally.
 *
 * Informal is canon.yaml's `address.sie` pattern (du, dich, dein…, euch,
 * euer…), plus "dir", the du-imperatives that start a sentence ("Wähle …",
 * "Prüfe …") and "Bitte jemanden/einen …, …" in the du-form of "bitten".
 * A new upstream label in the du-form fails here; override it in the file
 * Translation\FormalGermanLabels names, or, if it is no address, add it to
 * self::NOT_AN_ADDRESS with the reason.
 */
final class FormalGermanLabelsTest extends TestCase
{
    /** Composer package => extension key. */
    private const PACKAGES = [
        'netresearch/nr-mcp-agent' => 'nr_mcp_agent',
        'netresearch/t3-cowriter' => 't3_cowriter',
        'netresearch/nr-landingpage' => 'nr_landingpage',
        'netresearch/nr-browser-ai' => 'nr_browser_ai',
        'netresearch/nr-llm' => 'nr_llm',
        'netresearch/nr-vault' => 'nr_vault',
    ];

    /**
     * Labels the pattern flags although they address nobody. Key: the shipped
     * file as EXT: path, ":" and the unit id.
     *
     * @var array<string, string>
     */
    private const NOT_AN_ADDRESS = [];

    private const DU_IMPERATIVES = 'Achte|Aktiviere|Antworte|Bearbeite|Beachte|Beende|Behalte|Benutze|Beschreibe|Bestätige|Deaktiviere|Denk|Denke|Drücke|Entferne|Ergänze|Erstelle|Finde|Formuliere|Frag|Füge|Geh|Gehe|Gib|Halte|Hilf|Hinterlege|Klicke|Kopiere|Kontaktiere|Lade|Lass|Leg|Lege|Lies|Lösche|Mach|Mache|Markiere|Melde|Nimm|Nutze|Öffne|Probiere|Prüfe|Rufe|Sag|Schalte|Schau|Schick|Schicke|Schließe|Schreib|Schreibe|Setze|Sieh|Speichere|Starte|Stell|Suche|Teste|Tippe|Trage|Verbessere|Vergiss|Versuche|Verwende|Warte|Wechsle|Wähl|Wähle|Zeige|Zieh|Ziehe|Ändere';

    /** @var list<string>|null */
    private static ?array $patterns = null;

    /**
     * @return list<string>
     */
    private static function patterns(): array
    {
        if (self::$patterns === null) {
            $imperatives = self::DU_IMPERATIVES;
            $lowerImperatives = implode('|', array_map(static fn (string $word): string => mb_strtolower($word), explode('|', $imperatives)));
            self::$patterns = [
                '/' . Canon::fromFile()->address['sie'] . '/iu',
                '/\bdir\b/iu',
                '/(?:^|[.!?:;]\s+|[„"(–—]\s*)(?:' . $imperatives . ')\b/u',
                '/(?:[;:–—]\s*|\boder\s+|\bbitte\s+)(?:' . $lowerImperatives . ')\b/u',
                '/\bBitte\s+(?:jemanden|die|den|das|eine|einen)\b[^.;:]*?(?:\sum\b|,)/u',
            ];
        }

        return self::$patterns;
    }

    /**
     * @return list<string> the informal words or phrases found in $text
     */
    private static function informal(string $text): array
    {
        $found = [];
        foreach (self::patterns() as $pattern) {
            if (preg_match($pattern, $text, $match) === 1) {
                $found[] = trim($match[0]);
            }
        }

        return $found;
    }

    /**
     * @return array<string, array{source: string, target: string}> by unit id
     */
    private static function units(string $path): array
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load($path), $path . ' is no XML');
        $xpath = new \DOMXPath($document);
        $units = [];
        $nodes = $xpath->query('//*[local-name()="trans-unit" or local-name()="unit"]');
        self::assertInstanceOf(\DOMNodeList::class, $nodes);
        foreach ($nodes as $unit) {
            if (!$unit instanceof \DOMElement) {
                continue;
            }
            $source = $xpath->query('.//*[local-name()="source"]', $unit);
            $target = $xpath->query('.//*[local-name()="target"]', $unit);
            $units[$unit->getAttribute('id')] = [
                'source' => $source instanceof \DOMNodeList && $source->length > 0 ? (string)$source->item(0)?->textContent : '',
                'target' => $target instanceof \DOMNodeList && $target->length > 0 ? (string)$target->item(0)?->textContent : '',
            ];
        }

        return $units;
    }

    private static function packagePath(string $package): string
    {
        self::assertTrue(InstalledVersions::isInstalled($package), $package . ' is not installed');
        $path = InstalledVersions::getInstallPath($package);
        self::assertIsString($path);
        $real = realpath($path);
        self::assertIsString($real);

        return $real;
    }

    private static function extensionPath(string $extensionPath): string
    {
        self::assertSame(1, preg_match('#^EXT:([a-z0-9_]+)/(.+)$#', $extensionPath, $matches));
        if ($matches[1] === 'site_package') {
            return dirname(__DIR__, 3) . '/' . $matches[2];
        }
        $package = array_search($matches[1], self::PACKAGES, true);
        self::assertIsString($package, 'unknown extension ' . $matches[1]);

        return self::packagePath($package) . '/' . $matches[2];
    }

    /**
     * Every shipped German label file of the six packages, as EXT: path of
     * the default-language file => absolute path of the German file.
     *
     * @return array<string, string>
     */
    private static function shippedGermanFiles(): array
    {
        $files = [];
        foreach (self::PACKAGES as $package => $extensionKey) {
            $root = self::packagePath($package);
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || !str_starts_with($file->getFilename(), 'de.') || $file->getExtension() !== 'xlf') {
                    continue;
                }
                $relative = substr($file->getPathname(), strlen($root) + 1);
                if (!str_starts_with($relative, 'Resources/') && !str_starts_with($relative, 'Configuration/')) {
                    continue;
                }
                $shipped = 'EXT:' . $extensionKey . '/' . dirname($relative) . '/' . substr($file->getFilename(), 3);
                $files[$shipped] = $file->getPathname();
            }
        }
        ksort($files);

        return $files;
    }

    public function testNoGermanLabelOfTheAiStackAddressesTheReaderInformally(): void
    {
        $files = self::shippedGermanFiles();
        self::assertArrayHasKey('EXT:nr_mcp_agent/Resources/Private/Language/locallang_chat.xlf', $files);

        $failures = [];
        $checked = 0;
        foreach ($files as $shipped => $german) {
            $overrides = isset(FormalGermanLabels::OVERRIDES[$shipped])
                ? self::units(self::extensionPath(FormalGermanLabels::overridePath($shipped)))
                : [];
            foreach (self::units($german) as $id => $unit) {
                $target = $overrides[$id]['target'] ?? $unit['target'];
                $checked++;
                $found = self::informal($target);
                if ($found !== [] && !isset(self::NOT_AN_ADDRESS[$shipped . ':' . $id])) {
                    $failures[] = sprintf('%s:%s [%s] %s', $shipped, $id, implode(' | ', $found), $target);
                }
            }
        }

        self::assertGreaterThan(1000, $checked, 'the packages ship their German labels');
        self::assertSame([], $failures, "German labels in the du-form; override them (Translation\\FormalGermanLabels):\n" . implode("\n", $failures));
    }

    public function testEveryOverrideIsFormalAndMatchesTheShippedUnit(): void
    {
        foreach (FormalGermanLabels::OVERRIDES as $shipped => $file) {
            $override = self::units(self::extensionPath(FormalGermanLabels::overridePath($shipped)));
            self::assertNotSame([], $override, $file . ' has no units');
            $english = self::units(self::extensionPath($shipped));
            foreach ($override as $id => $unit) {
                self::assertArrayHasKey($id, $english, sprintf('%s overrides %s, which %s no longer has', $file, $id, $shipped));
                self::assertSame($english[$id]['source'], $unit['source'], sprintf('%s:%s: the English source changed upstream; check the German override', $file, $id));
                self::assertSame([], self::informal($unit['target']), sprintf('%s:%s is informal: %s', $file, $id, $unit['target']));
                self::assertNotSame('', trim($unit['target']), sprintf('%s:%s is empty', $file, $id));
                preg_match_all('/%(?:\d+\$)?[sd]/', $unit['source'], $sourcePlaceholders);
                preg_match_all('/%(?:\d+\$)?[sd]/', $unit['target'], $targetPlaceholders);
                self::assertSame($sourcePlaceholders[0], $targetPlaceholders[0], sprintf('%s:%s keeps its placeholders', $file, $id));
            }
        }
    }

    public function testEveryNotAnAddressEntryIsStillNeeded(): void
    {
        $files = self::shippedGermanFiles();
        foreach (self::NOT_AN_ADDRESS as $key => $reason) {
            self::assertNotSame('', $reason);
            $separator = strrpos($key, '.xlf:');
            self::assertIsInt($separator, $key);
            $shipped = substr($key, 0, $separator + 4);
            $id = substr($key, $separator + 5);
            self::assertArrayHasKey($shipped, $files, $key);
            $units = self::units($files[$shipped]);
            self::assertArrayHasKey($id, $units, $key);
            self::assertNotSame([], self::informal($units[$id]['target']), $key . ' no longer matches; remove it from the list');
        }
    }

    public function testOverridesAreRegisteredByFileAndByDomain(): void
    {
        $registrations = FormalGermanLabels::registrations();
        $file = 'EXT:site_package/Resources/Private/Language/Overrides/de.nr_mcp_agent_chat.xlf';
        self::assertSame([$file], $registrations['EXT:nr_mcp_agent/Resources/Private/Language/locallang_chat.xlf']);
        self::assertSame([$file], $registrations['nr_mcp_agent.chat']);
        self::assertSame(['EXT:site_package/Resources/Private/Language/Overrides/de.nr_llm.xlf'], $registrations['nr_llm.messages']);
        self::assertSame('nr_landingpage.db', FormalGermanLabels::domain('EXT:nr_landingpage/Resources/Private/Language/locallang_db.xlf'));

        $localconf = (string)file_get_contents(dirname(__DIR__, 3) . '/ext_localconf.php');
        self::assertStringContainsString("['LANG']['resourceOverrides']['de'][\$formalLabelKey][] = \$formalLabelFile", $localconf);
    }

    public function testThePatternCatchesTheShippedWordingAndLetsTheFormalOnePass(): void
    {
        self::assertNotSame([], self::informal('Wartet auf deine Freigabe'));
        self::assertNotSame([], self::informal('Von dir freigegeben'));
        self::assertNotSame([], self::informal('Wähle eine Seite, und wir gehen sie gemeinsam durch.'));
        self::assertNotSame([], self::informal('Der Assistent wartet. Antworte mit einer Nachricht oder wähle „Weiter“.'));
        self::assertNotSame([], self::informal('Sie wurde neu geladen; bearbeite die Nachricht bitte noch einmal.'));
        self::assertNotSame([], self::informal('Abgeschaltet. Bitte die Administration, es wieder freizugeben.'));
        self::assertNotSame([], self::informal('Bitte einen Administrator um eine andere Konfiguration.'));

        self::assertSame([], self::informal('Wartet auf Ihre Freigabe'));
        self::assertSame([], self::informal('Wählen Sie eine Seite, und wir gehen sie gemeinsam durch.'));
        self::assertSame([], self::informal('Leere Felder verwenden die der Installation.'));
        self::assertSame([], self::informal('Bitte die Karte prüfen und erneut entscheiden.'));
        self::assertSame([], self::informal('Ich habe die Befunde geprüft und akzeptiere sie.'));
        self::assertSame([], self::informal('Freigaben und Widerrufe benennen eine Version über diesen Wert.'));
    }
}
