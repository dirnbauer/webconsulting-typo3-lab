<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\ContentAudit;

/**
 * Checks one string against the canon.
 *
 * Errors are what the style guide calls hard: limits, retired facts, banned
 * words, test leftovers, US spellings and the wrong form of address. Warnings
 * are the soft targets. Legal pages are exempt from the sentence-length
 * limit, because their wording is not ours to shorten.
 */
final readonly class CanonChecker
{
    public function __construct(private Canon $canon) {}

    /**
     * @param string $text plain text (see CopyMetrics::plainText())
     * @param string $language `en` or `de`
     * @param string $siteKey key under `sites` in the canon, '' for none
     * @return list<Finding>
     */
    public function check(string $text, FieldRole $role, string $language, string $siteKey = '', bool $legal = false): array
    {
        $text = trim($text);
        if ($text === '' || $role === FieldRole::Skip) {
            return [];
        }

        $findings = [];
        array_push($findings, ...$this->checkLimits($text, $role, $language, $legal));
        array_push($findings, ...$this->checkPhrases($text, $role, $language, $siteKey));

        return $findings;
    }

    /**
     * @return list<Finding>
     */
    private function checkLimits(string $text, FieldRole $role, string $language, bool $legal): array
    {
        $findings = [];
        $metrics = CopyMetrics::measure($text);
        $chars = mb_strlen($text);
        $hard = $this->canon->hardLimits[$role->value] ?? [];
        $soft = $this->canon->softLimits[$role->value] ?? [];

        $maxChars = $language === 'de' && isset($hard['chars_de']) ? $hard['chars_de'] : ($hard['chars'] ?? null);
        if ($maxChars !== null && $chars > $maxChars) {
            $findings[] = new Finding('error', 'length', sprintf('%s has %d characters, the limit is %d', $role->value, $chars, $maxChars), $text);
        } elseif (isset($soft['chars']) && $chars > $soft['chars']) {
            $findings[] = new Finding('warning', 'length', sprintf('%s has %d characters, aim for %d', $role->value, $chars, $soft['chars']), $text);
        }

        $maxWords = $language === 'de' && isset($hard['words_de']) ? $hard['words_de'] : ($hard['words'] ?? null);
        if ($maxWords !== null && $metrics['words'] > $maxWords) {
            $findings[] = new Finding('error', 'words', sprintf('%s has %d words, the limit is %d', $role->value, $metrics['words'], $maxWords), $text);
        } elseif (isset($soft['words']) && $metrics['words'] > $soft['words']) {
            $findings[] = new Finding('warning', 'words', sprintf('%s has %d words, aim for %d', $role->value, $metrics['words'], $soft['words']), $text);
        }
        if (isset($soft['sentences']) && $metrics['sentences'] > $soft['sentences']) {
            $findings[] = new Finding('warning', 'sentences', sprintf('%s has %d sentences, aim for %d', $role->value, $metrics['sentences'], $soft['sentences']), $text);
        }
        if (isset($soft['min_chars']) && $chars < $soft['min_chars']) {
            $findings[] = new Finding('warning', 'length', sprintf('%s has %d characters, aim for at least %d', $role->value, $chars, $soft['min_chars']), $text);
        }
        if (isset($soft['max_chars']) && $chars > $soft['max_chars']) {
            $findings[] = new Finding('warning', 'length', sprintf('%s has %d characters, aim for at most %d', $role->value, $chars, $soft['max_chars']), $text);
        }

        $sentenceLimit = $this->canon->hardLimits['sentence']['words'] ?? null;
        if ($sentenceLimit !== null && !$legal) {
            foreach (CopyMetrics::sentences($text) as $sentence) {
                $words = count(CopyMetrics::words($sentence));
                if ($words > $sentenceLimit) {
                    $findings[] = new Finding('error', 'sentence', sprintf('sentence has %d words, the limit is %d', $words, $sentenceLimit), $sentence);
                }
            }
        }
        if (str_contains($text, '!') && $role !== FieldRole::Skip) {
            $findings[] = new Finding('warning', 'exclamation', 'avoid exclamation marks', $text);
        }

        return $findings;
    }

    /**
     * @return list<Finding>
     */
    private function checkPhrases(string $text, FieldRole $role, string $language, string $siteKey): array
    {
        $findings = [];

        foreach ($this->canon->forbidden as $rule) {
            if ($rule['sites'] !== [] && !in_array($siteKey, $rule['sites'], true)) {
                continue;
            }
            if (preg_match('/' . $rule['pattern'] . '/iu', $text, $match) === 1) {
                $findings[] = new Finding('error', 'fact', $rule['message'], $match[0]);
            }
        }

        foreach ($this->canon->bannedHard as $word) {
            if ($this->containsWord($text, $word)) {
                $findings[] = new Finding('error', 'banned', sprintf('"%s" is banned', $word), $word);
            }
        }
        foreach ($this->canon->bannedSoft as $word) {
            if ($this->containsWord($text, $word)) {
                $findings[] = new Finding('warning', 'vague', sprintf('"%s" is usually vague', $word), $word);
            }
        }

        // Leftovers anchored at the start or end only make sense for short
        // fields; the unanchored ones apply everywhere.
        foreach ($this->canon->leftovers as $pattern) {
            if (preg_match('/' . $pattern . '/iu', $text, $match) === 1) {
                $findings[] = new Finding('error', 'leftover', 'test leftover', $match[0]);
            }
        }

        if ($language === 'en') {
            foreach ($this->canon->spellingUk as $us => $uk) {
                if ($this->containsWord($text, $us)) {
                    $findings[] = new Finding('error', 'spelling', sprintf('British English: "%s", not "%s"', $uk, $us), $us);
                }
            }
        }

        $site = $siteKey !== '' ? $this->canon->site($siteKey) : null;
        if ($language === 'de' && $site !== null && $site['address'] !== '') {
            $pattern = $this->canon->address[$site['address']] ?? '';
            if ($pattern !== '' && preg_match('/' . $pattern . '/u', $text, $match) === 1) {
                $findings[] = new Finding('error', 'address', sprintf('this site addresses readers with "%s"', $site['address']), $match[0]);
            }
        }

        foreach ($this->canon->glossary[$language] ?? [] as $avoid => $use) {
            if ($avoid !== $use && $this->containsWord($text, $avoid)) {
                $findings[] = new Finding('warning', 'glossary', sprintf('write "%s", not "%s"', $use, $avoid), $avoid);
            }
        }

        return $findings;
    }

    private function containsWord(string $text, string $word): bool
    {
        // Word boundaries that also hold next to hyphens and umlauts.
        return preg_match('/(?<![\p{L}\p{N}_-])' . preg_quote($word, '/') . '(?![\p{L}\p{N}_-])/iu', $text) === 1;
    }
}
