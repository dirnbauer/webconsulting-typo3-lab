<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\AiStack;

/**
 * Writes TCA records. Production code uses DataHandler.
 */
interface RecordWriter
{
    /**
     * @param array<string, int|float|string> $fields
     * @return int the uid of the new record
     */
    public function create(string $table, array $fields): int;

    /**
     * @param array<string, int|float|string> $fields
     */
    public function update(string $table, int $uid, array $fields): void;

    /** Moves the record directly behind another record of the same table and page. */
    public function moveAfter(string $table, int $uid, int $afterUid): void;
}
