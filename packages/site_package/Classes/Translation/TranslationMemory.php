<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Translation;

/**
 * Translations of a site's copy into one language, keyed by the source text.
 *
 * Seeded sites get new record uids on every reseed, so a translation cannot
 * point at a row. It points at what the row says instead: the same English
 * headline gets the same German headline wherever it appears, and a source
 * text that changed has no translation until someone writes one, which the
 * command reports instead of showing a stale translation.
 *
 * Keys are compared after the same whitespace normalisation the content apply
 * command uses, because the RTE transformation rewrites whitespace between tags.
 * Link targets inside TYPO3 (`t3://page?uid=…`) do not count either: a seeder
 * that recreates pages gives them new uids, and the translation takes the
 * targets of the text it translates, whatever the memory recorded.
 */
final class TranslationMemory
{
    private const LINK_TARGET = '#t3://[^"\'\s<>]*#';

    /** @var array<string, string> normalised source => translation */
    private array $strings = [];

    /** @var array<string, string> normalised source => source as written in the memory */
    private array $sources = [];

    /** @var array<string, true> */
    private array $used = [];

    /**
     * @param array<string, string> $strings source => translation
     */
    public function __construct(public readonly string $language, array $strings = [])
    {
        foreach ($strings as $source => $translation) {
            $key = self::key((string)$source);
            $this->strings[$key] = $translation;
            $this->sources[$key] = (string)$source;
        }
    }

    /**
     * Reads `{"language": "de", "strings": {"source": "translation"}}`. A file
     * that does not exist yet is an empty memory, so the command can export
     * everything a new language needs.
     */
    public static function fromFile(string $path, string $language): self
    {
        if (!is_file($path)) {
            return new self($language);
        }
        $data = json_decode((string)file_get_contents($path), true);
        if (!is_array($data) || !is_array($data['strings'] ?? null)) {
            throw new \InvalidArgumentException(sprintf('%s is not a translation memory ({"strings": {…}}).', $path), 1790100001);
        }
        $strings = [];
        foreach ($data['strings'] as $source => $translation) {
            if (is_string($translation)) {
                $strings[(string)$source] = $translation;
            }
        }

        return new self($language, $strings);
    }

    /**
     * The translation of a source text, the text itself when it has nothing
     * to translate (a number, a percentage), or null when it is missing.
     */
    public function translate(string $source): ?string
    {
        $key = self::key($source);
        if ($key === '') {
            return $source;
        }
        if (isset($this->strings[$key])) {
            $this->used[$key] = true;

            return self::retarget($this->strings[$key], $this->sources[$key], $source);
        }

        return self::isLanguageNeutral($key) ? $source : null;
    }

    /**
     * The lookup key: whitespace-normalised, with every t3:// target blanked.
     */
    public static function key(string $source): string
    {
        return (string)preg_replace(self::LINK_TARGET, 't3://', self::normalise($source));
    }

    /**
     * Points the translation's t3:// links where the current source points:
     * the n-th target of the recorded source becomes the n-th target of the
     * current one, wherever the translation placed that link.
     */
    private static function retarget(string $translation, string $recorded, string $current): string
    {
        preg_match_all(self::LINK_TARGET, $recorded, $old);
        preg_match_all(self::LINK_TARGET, $current, $new);
        if ($old[0] === $new[0] || count($old[0]) !== count($new[0])) {
            return $translation;
        }
        $map = [];
        foreach ($old[0] as $index => $target) {
            $map[$target] ??= $new[0][$index];
        }

        return (string)preg_replace_callback(
            self::LINK_TARGET,
            static fn (array $match): string => $map[$match[0]] ?? $match[0],
            $translation
        );
    }

    public function count(): int
    {
        return count($this->strings);
    }

    /**
     * Entries no record asked for in this run: source texts that changed or
     * were removed since the translation was written.
     *
     * @return list<string>
     */
    public function unused(): array
    {
        return array_values(array_filter(
            array_keys($this->strings),
            fn (string $key): bool => !isset($this->used[$key])
        ));
    }

    /**
     * Text without letters reads the same in every language: "244", "99.9 %",
     * "2026", "→". Amounts are the exception, because German writes "49 €".
     */
    public static function isLanguageNeutral(string $text): bool
    {
        $plain = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_match('/\p{L}|\p{Sc}/u', $plain) !== 1;
    }

    public static function normalise(string $value): string
    {
        return trim((string)preg_replace(['/>\s+</u', '/\s+/u'], ['><', ' '], $value));
    }
}
