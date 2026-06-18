<?php

declare(strict_types=1);

namespace Webconsulting\Flue\Service;

use TYPO3\CMS\Core\Database\ConnectionPool;
use Webconsulting\Flue\Domain\Model\FlueEvent;
use Webconsulting\Flue\Domain\Model\FlueRun;
use Webconsulting\Flue\Enum\RunStatus;
use Webconsulting\Flue\Support\Typed;

/**
 * Persists durable runs into tx_flue_run as an append-only mirror of the
 * sidecar's Flue events. Status transitions use compare-and-set (UPDATE ...
 * WHERE status = expected) so the trigger request and the stream loop never
 * clobber each other.
 */
final class RunStore
{
    private const TABLE = 'tx_flue_run';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function create(int $flowUid, string $runKey, string $table, int $uid, int $workspaceId, string $instructions, array $payload, int $beUser): int
    {
        $now = time();
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        $connection->insert(self::TABLE, [
            'pid' => 0,
            'crdate' => $now,
            'tstamp' => $now,
            'flow' => $flowUid,
            'be_user' => $beUser,
            'run_key' => $runKey,
            'target_table' => $table,
            'target_uid' => $uid,
            'workspace_uid' => $workspaceId,
            'instructions' => mb_substr($instructions, 0, 65535),
            'status' => RunStatus::Submitted->value,
            'payload' => (string)json_encode($payload, JSON_UNESCAPED_SLASHES),
            'events' => '[]',
            'started' => $now,
        ]);

        return (int)$connection->lastInsertId();
    }

    public function setFlueRunId(int $runUid, string $flueRunId): void
    {
        if ($flueRunId === '') {
            return;
        }
        $this->connectionPool->getConnectionForTable(self::TABLE)
            ->update(self::TABLE, ['flue_run_id' => $flueRunId, 'tstamp' => time()], ['uid' => $runUid]);
    }

    public function appendEvent(int $runUid, FlueEvent $event): void
    {
        $run = $this->load($runUid);
        if ($run === null) {
            return;
        }
        $events = array_map(static fn (FlueEvent $e): array => $e->toArray(), $run->events);
        $events[] = $event->toArray();

        $this->connectionPool->getConnectionForTable(self::TABLE)->update(
            self::TABLE,
            ['events' => (string)json_encode($events, JSON_UNESCAPED_SLASHES), 'tstamp' => time()],
            ['uid' => $runUid],
        );
    }

    /** Compare-and-set submitted -> running. Returns false if another writer moved it. */
    public function markRunning(int $runUid): bool
    {
        return $this->casStatus($runUid, RunStatus::Submitted, RunStatus::Running) > 0;
    }

    public function markSettled(int $runUid, string $output, string $usageJson = '', string $resultJson = '', string $verdict = ''): void
    {
        $this->connectionPool->getConnectionForTable(self::TABLE)->update(
            self::TABLE,
            [
                'status' => RunStatus::Settled->value,
                'output' => mb_substr($output, 0, 16000000),
                'usage_json' => $usageJson,
                'result_json' => $resultJson,
                'verdict' => mb_substr($verdict, 0, 32),
                'finished' => time(),
                'tstamp' => time(),
            ],
            ['uid' => $runUid],
        );
    }

    public function markFailed(int $runUid, string $error): void
    {
        $this->connectionPool->getConnectionForTable(self::TABLE)->update(
            self::TABLE,
            [
                'status' => RunStatus::Failed->value,
                'error_message' => mb_substr($error, 0, 65535),
                'finished' => time(),
                'tstamp' => time(),
            ],
            ['uid' => $runUid],
        );
    }

    public function load(int $runUid): ?FlueRun
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $qb->getRestrictions()->removeAll();
        $row = $qb->select('*')->from(self::TABLE)
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($runUid, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)))
            ->executeQuery()->fetchAssociative();

        return is_array($row) ? FlueRun::fromRow($row) : null;
    }

    public function loadByRunKey(string $runKey): ?FlueRun
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $qb->getRestrictions()->removeAll();
        $row = $qb->select('*')->from(self::TABLE)
            ->where($qb->expr()->eq('run_key', $qb->createNamedParameter($runKey)))
            ->orderBy('uid', 'DESC')->setMaxResults(1)
            ->executeQuery()->fetchAssociative();

        return is_array($row) ? FlueRun::fromRow($row) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 25): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $rows = $qb->select('*')->from(self::TABLE)
            ->orderBy('crdate', 'DESC')->setMaxResults($limit)
            ->executeQuery()->fetchAllAssociative();

        return array_map(static fn (array $row): array => Typed::stringKeyedArray($row), $rows);
    }

    /**
     * @return list<int>
     */
    public function findResumable(): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $rows = $qb->select('uid')->from(self::TABLE)
            ->where($qb->expr()->in('status', $qb->createNamedParameter(
                [RunStatus::Running->value, RunStatus::Failed->value, RunStatus::Resumable->value],
                \TYPO3\CMS\Core\Database\Connection::PARAM_STR_ARRAY,
            )))
            ->executeQuery()->fetchFirstColumn();

        return array_map(static fn (mixed $v): int => Typed::int($v), $rows);
    }

    private function casStatus(int $runUid, RunStatus $from, RunStatus $to): int
    {
        return (int)$this->connectionPool->getConnectionForTable(self::TABLE)->update(
            self::TABLE,
            ['status' => $to->value, 'tstamp' => time()],
            ['uid' => $runUid, 'status' => $from->value],
        );
    }
}
