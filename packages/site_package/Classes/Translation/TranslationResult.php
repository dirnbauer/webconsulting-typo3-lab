<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Translation;

/**
 * What one translate run did for one language, and what it could not do.
 */
final readonly class TranslationResult
{
    /**
     * @param array<string, int> $counts pagesLocalized, recordsLocalized, childrenSynchronized, recordsUpdated, fieldsUpdated
     * @param list<array{source: string, role: string, where: list<string>}> $missing source texts without a translation
     * @param list<string> $unused memory entries no record asked for
     * @param array<string, array<string, string>> $copied table.field => text => where: kept as it is, but reads like copy
     */
    public function __construct(
        public string $language,
        public array $counts,
        public array $missing,
        public array $unused,
        public array $copied = [],
    ) {}
}
