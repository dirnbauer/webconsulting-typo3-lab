<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Translation;

/**
 * German labels of the Netresearch AI stack in the "Sie" form.
 *
 * nr-mcp-agent, nr-llm and nr-landingpage ship German labels that address the
 * reader with "du" ("Wartet auf deine Freigabe", "Wähle eine Seite …"). The
 * lab's German copy uses "Sie", so site_package overrides exactly those units
 * through TYPO3's label overrides ($GLOBALS['TYPO3_CONF_VARS']['LANG']
 * ['resourceOverrides'], the v14 name of locallangXMLOverride), registered in
 * ext_localconf.php under the file and its translation domain. The chat's web
 * components read the same labels: the backend hands locallang_chat.xlf to
 * TYPO3.lang (addInlineLanguageLabelFile), which applies the overrides too.
 *
 * Tests/Unit/Translation/FormalGermanLabelsTest.php fails when a shipped
 * German label of these packages, or an override, still addresses the reader
 * informally.
 */
final class FormalGermanLabels
{
    private const OVERRIDE_DIRECTORY = 'EXT:site_package/Resources/Private/Language/Overrides/';

    /**
     * Shipped label file => override file name in OVERRIDE_DIRECTORY.
     *
     * @var array<string, string>
     */
    public const OVERRIDES = [
        'EXT:nr_mcp_agent/Resources/Private/Language/locallang_chat.xlf' => 'de.nr_mcp_agent_chat.xlf',
        'EXT:nr_mcp_agent/Resources/Private/Language/locallang_dashboard.xlf' => 'de.nr_mcp_agent_dashboard.xlf',
        'EXT:nr_mcp_agent/Resources/Private/Language/locallang_alttext.xlf' => 'de.nr_mcp_agent_alttext.xlf',
        'EXT:nr_llm/Resources/Private/Language/locallang.xlf' => 'de.nr_llm.xlf',
        'EXT:nr_landingpage/Resources/Private/Language/locallang_db.xlf' => 'de.nr_landingpage_db.xlf',
    ];

    /**
     * The registrations for $GLOBALS['TYPO3_CONF_VARS']['LANG']['resourceOverrides']['de']:
     * every shipped file by path and by its translation domain
     * ("nr_mcp_agent.chat", "nr_llm.messages"), because a label can be
     * requested either way.
     *
     * @return array<string, list<string>>
     */
    public static function registrations(): array
    {
        $registrations = [];
        foreach (self::OVERRIDES as $shipped => $override) {
            $file = self::OVERRIDE_DIRECTORY . $override;
            $registrations[$shipped][] = $file;
            $registrations[self::domain($shipped)][] = $file;
        }

        return $registrations;
    }

    /**
     * The translation domain TYPO3 derives from a file in
     * Resources/Private/Language/ (TranslationDomainResolver): locallang.xlf
     * is "<ext>.messages", locallang_<name>.xlf is "<ext>.<name>".
     */
    public static function domain(string $shipped): string
    {
        if (preg_match('#^EXT:([a-z0-9_]+)/Resources/Private/Language/([A-Za-z0-9_]+)\.xlf$#', $shipped, $matches) !== 1) {
            throw new \InvalidArgumentException(sprintf('Not a label file in Resources/Private/Language/: %s', $shipped), 1795700001);
        }
        $name = $matches[2] === 'locallang' ? 'messages' : (string)preg_replace('/^locallang_/', '', $matches[2]);

        return $matches[1] . '.' . $name;
    }

    public static function overridePath(string $shipped): string
    {
        return self::OVERRIDE_DIRECTORY . self::OVERRIDES[$shipped];
    }
}
