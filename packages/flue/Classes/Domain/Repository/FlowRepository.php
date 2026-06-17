<?php

declare(strict_types=1);

namespace Webconsulting\Flue\Domain\Repository;

use TYPO3\CMS\Core\Database\ConnectionPool;
use Webconsulting\Flue\Support\Typed;

/**
 * Thin read access to tx_flue_flow definitions.
 */
final class FlowRepository
{
    private const TABLE = 'tx_flue_flow';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByUid(int $uid): ?array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $row = $qb->select('*')->from(self::TABLE)
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)))
            ->executeQuery()->fetchAssociative();

        return is_array($row) ? Typed::stringKeyedArray($row) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $rows = $qb->select('*')->from(self::TABLE)
            ->orderBy('title')
            ->executeQuery()->fetchAllAssociative();

        return array_map(static fn (array $row): array => Typed::stringKeyedArray($row), $rows);
    }
}
