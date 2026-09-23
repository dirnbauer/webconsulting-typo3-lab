<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\ContentAudit;

/**
 * Measures how hard a piece of copy is to read.
 *
 * Pure functions over plain text, so the database audit and the seed-file lint
 * measure a string the same way. Readability is LIX (words per sentence plus
 * the share of words longer than six letters): it needs no syllable counting
 * and gives comparable numbers for English and German.
 */
final class CopyMetrics
{
    /**
     * Abbreviations whose full stop does not end a sentence.
     */
    private const ABBREVIATIONS = ['e.g', 'i.e', 'etc', 'vs', 'z.B', 'd.h', 'bzw', 'ca', 'Nr', 'inkl', 'exkl', 'u.a', 'usw', 'Dr', 'Mr', 'Mrs', 'Ms', 'St', 'No'];

    public static function plainText(string $value): string
    {
        // Block-level tags end a sentence-like unit even without punctuation:
        // list items and headings rarely carry a full stop.
        $value = (string)preg_replace('#</(p|li|h[1-6]|dt|dd|td|th|blockquote|div)>|<br\s*/?>#i', "\n", $value);
        $value = strip_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace("\u{00A0}", ' ', $value);
        $value = (string)preg_replace('/[ \t]+/u', ' ', $value);
        $value = (string)preg_replace('/\s*\n\s*/u', "\n", $value);

        return trim($value);
    }

    /**
     * @return list<string>
     */
    public static function words(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'’\-]*/u', $text, $matches);

        return array_values(array_filter(
            $matches[0],
            static fn (string $word): bool => preg_match('/\p{L}/u', $word) === 1 || preg_match('/^\p{N}+$/u', $word) === 1
        ));
    }

    /**
     * Splits on sentence punctuation and on line breaks. A line without
     * punctuation (a list item, a heading) counts as its own sentence.
     *
     * @return list<string>
     */
    public static function sentences(string $text): array
    {
        $protected = $text;
        foreach (self::ABBREVIATIONS as $index => $abbreviation) {
            $protected = (string)preg_replace('/\b' . preg_quote($abbreviation, '/') . '\./u', '§' . $index . '§', $protected);
        }
        // Thousands separators and German dates ("1.490 €", "11. September") are not sentence ends.
        $protected = (string)preg_replace('/(\d)\.(?=\d)/u', '$1¤', $protected);
        $protected = (string)preg_replace('/\b(\d{1,2})\.(?=\s+(Jänner|Januar|Februar|März|April|Mai|Juni|Juli|August|September|Oktober|November|Dezember)\b)/u', '$1¤', $protected);

        $parts = preg_split('/(?<=[.!?…])\s+|\n+/u', $protected) ?: [];
        $sentences = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || self::words($part) === []) {
                continue;
            }
            $part = str_replace('¤', '.', $part);
            $part = (string)preg_replace_callback('/§(\d+)§/u', static fn (array $m): string => self::ABBREVIATIONS[(int)$m[1]] . '.', $part);
            $sentences[] = $part;
        }

        return $sentences;
    }

    /**
     * @return array{words: int, sentences: int, longWords: int, avgSentenceWords: float, maxSentenceWords: int, lix: float}
     */
    public static function measure(string $text): array
    {
        $words = self::words($text);
        $sentences = self::sentences($text);
        $wordCount = count($words);
        $sentenceCount = max(1, count($sentences));
        $longWords = count(array_filter($words, static fn (string $word): bool => mb_strlen($word) > 6));
        $maxSentence = 0;
        foreach ($sentences as $sentence) {
            $maxSentence = max($maxSentence, count(self::words($sentence)));
        }

        return [
            'words' => $wordCount,
            'sentences' => $wordCount === 0 ? 0 : $sentenceCount,
            'longWords' => $longWords,
            'avgSentenceWords' => $wordCount === 0 ? 0.0 : round($wordCount / $sentenceCount, 1),
            'maxSentenceWords' => $maxSentence,
            'lix' => self::lix($wordCount, $sentenceCount, $longWords),
        ];
    }

    public static function lix(int $words, int $sentences, int $longWords): float
    {
        if ($words === 0) {
            return 0.0;
        }

        return round($words / max(1, $sentences) + 100 * $longWords / $words, 1);
    }
}
