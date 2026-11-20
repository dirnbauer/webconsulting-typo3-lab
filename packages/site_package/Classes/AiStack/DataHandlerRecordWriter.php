<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\AiStack;

use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Writes through DataHandler as the current backend user (on the CLI the
 * _cli_ administrator after Bootstrap::initializeBackendAuthentication()), so
 * TCA evaluation, the extensions' DataHandler hooks and the history apply as
 * they do for a save in the backend.
 */
final class DataHandlerRecordWriter implements RecordWriter
{
    public function create(string $table, array $fields): int
    {
        $placeholder = 'NEW' . bin2hex(random_bytes(6));
        $dataHandler = $this->process([$table => [$placeholder => $fields]]);
        $uid = $dataHandler->substNEWwithIDs[$placeholder] ?? 0;
        if (!is_numeric($uid) || (int)$uid <= 0) {
            throw new \RuntimeException(sprintf('DataHandler did not create the %s record.', $table), 1795600001);
        }

        return (int)$uid;
    }

    public function update(string $table, int $uid, array $fields): void
    {
        $this->process([$table => [$uid => $fields]]);
    }

    public function moveAfter(string $table, int $uid, int $afterUid): void
    {
        $this->process([], [$table => [$uid => ['move' => -$afterUid]]]);
    }

    /**
     * @param array<string, array<int|string, array<string, int|float|string>>> $data
     * @param array<string, array<int, array<string, int>>> $commands
     */
    private function process(array $data, array $commands = []): DataHandler
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, $commands);
        $dataHandler->process_datamap();
        $dataHandler->process_cmdmap();
        if ($dataHandler->errorLog !== []) {
            throw new \RuntimeException('DataHandler refused the AI stack records: ' . implode(' ', $dataHandler->errorLog), 1795600002);
        }

        return $dataHandler;
    }
}
