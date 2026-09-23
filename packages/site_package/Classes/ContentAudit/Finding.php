<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\ContentAudit;

/**
 * One rule a string breaks. `error` fails a check run, `warning` is reported.
 */
final readonly class Finding
{
    public function __construct(
        public string $severity,
        public string $rule,
        public string $message,
        public string $excerpt = '',
    ) {}

    public function isError(): bool
    {
        return $this->severity === 'error';
    }

    /**
     * @return array{severity: string, rule: string, message: string, excerpt: string}
     */
    public function toArray(): array
    {
        return [
            'severity' => $this->severity,
            'rule' => $this->rule,
            'message' => $this->message,
            'excerpt' => $this->excerpt,
        ];
    }
}
