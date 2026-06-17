<?php

declare(strict_types=1);

namespace Webconsulting\Flue\Service;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use Webconsulting\Flue\Support\Typed;

/**
 * Resolves a fixed, closed set of placeholder tokens and substitutes them into
 * a flow's instructions before a run is triggered. Mirrors skillflow's resolver:
 * a single strtr() pass over a whitelist — no expression evaluation — so a
 * record value containing a brace can never trigger a second resolution pass.
 *
 * Supported tokens: {uid} {table} {pid} {title} {workspace}
 */
final class ContextResolver
{
    /**
     * @return array<string, string> token => already-stringified value
     */
    public function resolveTokens(string $table, int $uid, int $workspaceId): array
    {
        $record = [];
        if ($uid > 0) {
            $fetched = BackendUtility::getRecord($table, $uid);
            if (is_array($fetched)) {
                $record = $fetched;
            }
        }

        return [
            '{uid}' => (string)$uid,
            '{table}' => $table,
            '{pid}' => (string)Typed::int($record['pid'] ?? 0),
            '{title}' => Typed::string($record[$this->resolveLabelField($table)] ?? ''),
            '{workspace}' => (string)$workspaceId,
        ];
    }

    /**
     * @param array<string, string> $tokens
     */
    public function apply(string $template, array $tokens): string
    {
        return strtr($template, $tokens);
    }

    private function resolveLabelField(string $table): string
    {
        $tca = $GLOBALS['TCA'] ?? null;
        if (!is_array($tca)) {
            return 'title';
        }
        $tableTca = $tca[$table] ?? null;
        if (!is_array($tableTca)) {
            return 'title';
        }
        $ctrl = $tableTca['ctrl'] ?? null;
        if (!is_array($ctrl)) {
            return 'title';
        }
        $label = $ctrl['label'] ?? null;

        return is_string($label) && $label !== '' ? $label : 'title';
    }
}
