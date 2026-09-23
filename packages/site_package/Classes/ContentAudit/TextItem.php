<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\ContentAudit;

/**
 * One text value a site shows: which row and field it lives in, which page
 * and element it belongs to, and what the field is for.
 */
final readonly class TextItem
{
    public function __construct(
        public string $table,
        public int $uid,
        public string $field,
        public string $value,
        public int $pageUid,
        public int $elementUid,
        public string $elementType,
        public FieldRole $role,
        public string $file = '',
    ) {}

    public function plainText(): string
    {
        return CopyMetrics::plainText($this->value);
    }

    public function key(): string
    {
        return $this->table . ':' . $this->uid . ':' . $this->field;
    }
}
