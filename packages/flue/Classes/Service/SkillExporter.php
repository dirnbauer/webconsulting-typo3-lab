<?php

declare(strict_types=1);

namespace Webconsulting\Flue\Service;

use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webconsulting\Flue\Support\Typed;

/**
 * Materializes selected skillflow skills as a Flue `skills/<identifier>/SKILL.md`
 * directory (+ attachments) the sidecar reads. Soft-coupled to skillflow: reads
 * its tables directly and degrades gracefully when skillflow is absent, so flue
 * installs standalone. Mirrors skillflow's ClaudeCliRunner::materializeSkill().
 */
final class SkillExporter
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {
    }

    public function isAvailable(): bool
    {
        $tca = $GLOBALS['TCA'] ?? null;
        return is_array($tca) && isset($tca['tx_skillflow_skill']);
    }

    /**
     * @param list<int> $skillUids
     * @return list<string> written SKILL.md paths
     */
    public function export(array $skillUids, string $targetDir): array
    {
        if (!$this->isAvailable() || $skillUids === []) {
            return [];
        }

        $written = [];
        foreach ($skillUids as $skillUid) {
            $skill = $this->fetchSkill($skillUid);
            if ($skill === null) {
                continue;
            }
            $identifier = Typed::string($skill['identifier'] ?? '');
            if ($identifier === '' || !preg_match('/^[a-z0-9._-]+$/i', $identifier)) {
                continue;
            }
            $skillDir = rtrim($targetDir, '/') . '/' . $identifier;
            GeneralUtility::mkdir_deep($skillDir);

            $frontmatter = ['name' => $identifier, 'description' => Typed::string($skill['description'] ?? '')];
            $allowedTools = Typed::string($skill['allowed_tools'] ?? '');
            if (trim($allowedTools) !== '') {
                $frontmatter['allowed-tools'] = $allowedTools;
            }
            $markdown = "---\n" . Yaml::dump($frontmatter) . "---\n\n" . Typed::string($skill['body'] ?? '') . "\n";
            $skillMd = $skillDir . '/SKILL.md';
            GeneralUtility::writeFile($skillMd, $markdown);
            $written[] = $skillMd;

            $this->writeFiles($skillUid, $skillDir);
        }

        return $written;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchSkill(int $skillUid): ?array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_skillflow_skill');
        $row = $qb->select('identifier', 'description', 'body', 'allowed_tools')
            ->from('tx_skillflow_skill')
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($skillUid, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)))
            ->executeQuery()->fetchAssociative();

        return is_array($row) ? Typed::stringKeyedArray($row) : null;
    }

    private function writeFiles(int $skillUid, string $skillDir): void
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_skillflow_file');
        $rows = $qb->select('relative_path', 'content')
            ->from('tx_skillflow_file')
            ->where($qb->expr()->eq('skill', $qb->createNamedParameter($skillUid, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)))
            ->executeQuery()->fetchAllAssociative();

        foreach ($rows as $row) {
            $relative = Typed::string($row['relative_path'] ?? '');
            // Reject path traversal and absolute paths.
            if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/')) {
                continue;
            }
            $path = $skillDir . '/' . $relative;
            GeneralUtility::mkdir_deep(dirname($path));
            GeneralUtility::writeFile($path, Typed::string($row['content'] ?? ''));
        }
    }
}
