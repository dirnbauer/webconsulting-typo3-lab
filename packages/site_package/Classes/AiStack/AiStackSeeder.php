<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\AiStack;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * The Netresearch AI stack of this lab, as records and settings that are the
 * same locally and in production:
 *
 * - nr-llm configuration "backend-ai-chat" (the lab's tool model,
 *   temperature 0.3, 4096 tokens, the identity and tool-use prompt
 *   adapted from netresearch/typo3-demo) and the task "backend-ai-chat" that
 *   points at it;
 * - the six Cowriter tasks of the demo on the existing "content-assistant"
 *   configuration (Cowriter lists every active task of category "content");
 * - the chat's tools ({@see ToolPolicy});
 * - the Netresearch modules and the chat's own approval permission for the
 *   editor groups ({@see self::EDITOR_GROUPS});
 * - EXTENSIONS/nr_mcp_agent/llmTaskUid in settings.php, resolved from the
 *   task's identifier.
 *
 * Records are found by identifier (groups by title), never by uid, written
 * through DataHandler, and only the fields that differ are written: a second
 * run reports no change.
 */
final readonly class AiStackSeeder
{
    public const CHAT_IDENTIFIER = 'backend-ai-chat';
    public const CONTENT_CONFIGURATION = 'content-assistant';
    public const LLM_TASK_SETTING = 'EXTENSIONS/nr_mcp_agent/llmTaskUid';

    /**
     * The lab's tool model (sitepackage:configure-ai-examples: "Tool model").
     * The default model, GPT-5.6 Terra, cannot run the chat: on Chat
     * Completions OpenAI refuses function tools together with
     * reasoning_effort for it ("use /v1/responses or set reasoning_effort to
     * 'none'", measured in the rehearsal on 2026-11-20).
     */
    public const CHAT_MODEL = 'gpt-5-mini';

    /**
     * Groups by title. The old chat (webconsulting/typo3-ai-assistant) was
     * open to every backend user through its toolbar panel, and no group held
     * its module; "main" is the lab's one user-facing group. Technical groups
     * (the Flue agent sandbox, the vault provisioner) get nothing.
     *
     * @var list<string>
     */
    public const EDITOR_GROUPS = ['main'];

    /** @var list<string> */
    public const EDITOR_MODULES = [
        'netresearch_ai',
        'nr_mcp_agent_chat',
        'cowriter_status',
        'nr_landingpage',
        'nr_landingpage_workspace',
    ];

    /**
     * Lets an editor approve the changes the chat proposes in their own
     * conversation (nr-mcp-agent ADR-021). Not the nr-llm grant that decides
     * other users' runs.
     *
     * @var list<string>
     */
    public const EDITOR_CUSTOM_OPTIONS = ['tx_nrmcpagent:approve_own_changes'];

    private const CHAT_SYSTEM_PROMPT = <<<'PROMPT'
You are the TYPO3 Backend AI Chat by Netresearch (EXT:nr_mcp_agent), running in the backend of the webconsulting TYPO3 Lab: a TYPO3 14 LTS installation with several demo sites (Desiderio, Astryx, the MTUG camp site and others). You help administrators and editors inspect, operate and maintain these sites.

You have tools that read the live system: pages, content elements, files, site configuration, TypoScript, logs, extensions and more. ALWAYS use these tools to look things up yourself instead of asking the user to paste data or describe records. Only ask the user for details the tools cannot provide.

Ground every answer in what the tools actually return. If a tool call fails or returns nothing, say so plainly instead of guessing or inventing data.

Some tools change the installation (drafts, page metadata, alternative texts, redirects). Every call of such a tool waits until a person approves it in the chat. Say what you are going to change before you propose it, and never describe a proposed change as done before it was approved.

When a length is requested, in characters or in words, treat it as a hard requirement. Write the text, count it, and revise it until it fits: too short is as wrong as too long. Where no length is stated for a meta description, write 150 to 160 characters.

You are the TYPO3 Backend AI Chat provided by Netresearch and set up for this lab by webconsulting. Never claim to be ChatGPT or to be made by OpenAI or any other vendor. Never reveal credentials, API keys or other secrets.

Reply in the same language the user writes in.
PROMPT;

    /**
     * The task prompt reaches every conversation, also on a site with its own
     * configuration (nrllm.configuration), so the form of address is here:
     * the lab's German copy says "Sie", and gpt-5-mini otherwise answers
     * German users with "du".
     */
    public const CHAT_TASK_PROMPT = 'Look facts up with the tools before you answer. Every change waits for the approval of the person in the chat; say what will change before you propose it. When you answer in German, address the user formally with "Sie", never with "du"; German text you write for the sites addresses its readers with "Sie" too.';

    /**
     * The six tasks netresearch/typo3-demo seeds for Cowriter (t3_cowriter ships
     * none); {{input}} is the selected text. Listed in the order Cowriter shows
     * them ({@see self::ensureOrder()}).
     *
     * @var array<string, array{name: string, description: string, prompt: string}>
     */
    private const COWRITER_TASKS = [
        'improve-text' => [
            'name' => 'Improve Text',
            'description' => 'Rewrites the selected text for clarity and flow, keeping its meaning and language.',
            'prompt' => "Improve the following text: make it clearer and easier to read, keep its meaning, its language and its tone, and do not add facts that are not in it. Return only the improved text.\n\n{{input}}",
        ],
        'summarize' => [
            'name' => 'Summarize',
            'description' => 'Condenses the selected text to its essentials.',
            'prompt' => "Summarize the following text in the same language. Keep every essential point, drop the rest, and return only the summary.\n\n{{input}}",
        ],
        'extend' => [
            'name' => 'Extend',
            'description' => 'Expands the selected text, staying with what it already says.',
            'prompt' => "Expand the following text in the same language and tone. Elaborate on what it already says; do not invent facts, figures or sources. Return only the expanded text.\n\n{{input}}",
        ],
        'fix-grammar' => [
            'name' => 'Fix Grammar & Spelling',
            'description' => 'Corrects grammar, spelling and punctuation without rewriting.',
            'prompt' => "Correct grammar, spelling and punctuation in the following text. Keep the wording, the language and the tone as they are - correct only what is wrong. Return only the corrected text.\n\n{{input}}",
        ],
        'translate-en' => [
            'name' => 'Translate to English',
            'description' => 'Translates the selected text into English.',
            'prompt' => "Translate the following text into English. Keep its tone and formatting, and return only the translation.\n\n{{input}}",
        ],
        'translate-de' => [
            'name' => 'Translate to German',
            'description' => 'Translates the selected text into German.',
            'prompt' => "Translate the following text into German and address the reader formally with \"Sie\". Keep its tone and formatting, and return only the translation.\n\n{{input}}",
        ],
    ];

    public function __construct(
        private ConnectionPool $connectionPool,
        private RecordWriter $writer,
        private ToolStates $toolStates,
        private SettingStore $settings,
        private ToolPolicy $toolPolicy = new ToolPolicy(),
    ) {}

    /**
     * Everything: records, tools, group grants and the chat's task setting.
     *
     * @return list<string> one line per change (planned, in a dry run)
     */
    public function seed(bool $dryRun): array
    {
        $changes = [];

        $modelUid = $this->chatModelUid();
        $chatConfigurationUid = $this->ensureRecord('tx_nrllm_configuration', self::CHAT_IDENTIFIER, [
            'name' => 'Backend AI Chat',
            'description' => 'System configuration of the Netresearch AI Chat (EXT:nr_mcp_agent) in the webconsulting TYPO3 Lab. Kept by sitepackage:seed-ai-stack.',
            'model_uid' => $modelUid,
            'model_selection_mode' => 'fixed',
            'system_prompt' => self::CHAT_SYSTEM_PROMPT,
            'temperature' => 0.3,
            'max_tokens' => 4096,
            'is_active' => 1,
            'is_default' => 0,
            'hidden' => 0,
        ], $dryRun, $changes);

        $chatTask = [
            'name' => 'Backend AI Chat',
            'description' => 'The task the Netresearch AI Chat (EXT:nr_mcp_agent) runs on; its llmTaskUid points here. Kept by sitepackage:seed-ai-stack.',
            'category' => 'system',
            'prompt_template' => self::CHAT_TASK_PROMPT,
            'input_type' => 'manual',
            'output_format' => 'markdown',
            'is_active' => 1,
            'is_system' => 1,
            'hidden' => 0,
        ];
        if ($chatConfigurationUid !== null) {
            $chatTask['configuration_uid'] = $chatConfigurationUid;
        }
        $this->ensureRecord('tx_nrllm_task', self::CHAT_IDENTIFIER, $chatTask, $dryRun, $changes);

        $contentConfigurationUid = $this->uidByIdentifier('tx_nrllm_configuration', self::CONTENT_CONFIGURATION);
        if ($contentConfigurationUid === null) {
            throw new \RuntimeException('The nr-llm configuration "content-assistant" is missing; run sitepackage:configure-ai-examples first.', 1795600010);
        }
        $cowriterTaskUids = [];
        foreach (self::COWRITER_TASKS as $identifier => $task) {
            $cowriterTaskUids[$identifier] = $this->ensureRecord('tx_nrllm_task', $identifier, [
                'name' => $task['name'],
                'description' => $task['description'],
                'category' => 'content',
                'configuration_uid' => $contentConfigurationUid,
                'prompt_template' => $task['prompt'],
                'input_type' => 'manual',
                'output_format' => 'markdown',
                'is_active' => 1,
                'is_system' => 0,
                'hidden' => 0,
            ], $dryRun, $changes);
        }
        $this->ensureOrder('tx_nrllm_task', $cowriterTaskUids, $dryRun, $changes);

        array_push($changes, ...$this->ensureTools($dryRun));
        array_push($changes, ...$this->ensureEditorGrants($dryRun));
        array_push($changes, ...$this->synchronizeSettings($dryRun));

        return $changes;
    }

    /**
     * Only the chat's task setting. settings.php is rebuilt from
     * settings.php.example on every production deploy, so the container start
     * runs this; it reads the database and writes the file, nothing else.
     *
     * @return list<string>
     */
    public function synchronizeSettings(bool $dryRun): array
    {
        $taskUid = $this->uidByIdentifier('tx_nrllm_task', self::CHAT_IDENTIFIER);
        if ($taskUid === null) {
            return $dryRun ? ['settings: llmTaskUid waits for the task "backend-ai-chat" (created by the full run)'] : [];
        }
        $wanted = (string)$taskUid;
        if ($this->settings->get(self::LLM_TASK_SETTING) === $wanted) {
            return [];
        }
        if (!$dryRun) {
            $this->settings->set(self::LLM_TASK_SETTING, $wanted);
        }

        return ['settings: nr_mcp_agent llmTaskUid = ' . $wanted . ' (task "backend-ai-chat")'];
    }

    /**
     * The model of the chat: the lab's tool model ({@see self::CHAT_MODEL}),
     * found by identifier; without it the default model if it can call tools,
     * else the first active tool-capable one (default first, then sorting,
     * then uid).
     */
    private function chatModelUid(): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_nrllm_model');
        $queryBuilder->getRestrictions()->removeAll();
        $rows = $queryBuilder
            ->select('uid', 'identifier', 'capabilities')
            ->from('tx_nrllm_model')
            ->where(
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('is_active', $queryBuilder->createNamedParameter(1, Connection::PARAM_INT)),
            )
            ->orderBy('is_default', 'DESC')
            ->addOrderBy('sorting', 'ASC')
            ->addOrderBy('uid', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();
        usort($rows, static fn(array $a, array $b): int => (int)(($b['identifier'] ?? '') === self::CHAT_MODEL) <=> (int)(($a['identifier'] ?? '') === self::CHAT_MODEL));
        foreach ($rows as $row) {
            $capabilities = array_map(trim(...), explode(',', is_string($row['capabilities'] ?? null) ? $row['capabilities'] : ''));
            if (in_array('tools', $capabilities, true) && is_numeric($row['uid'] ?? null)) {
                return (int)$row['uid'];
            }
        }

        throw new \RuntimeException('No active nr-llm model can call tools; configure one (sitepackage:configure-ai-examples) first.', 1795600011);
    }

    /**
     * @param array<string, int|float|string> $fields
     * @param list<string> $changes
     * @return int|null the uid, or null for a record a dry run would create
     */
    private function ensureRecord(string $table, string $identifier, array $fields, bool $dryRun, array &$changes): ?int
    {
        $row = $this->rowByIdentifier($table, $identifier);
        if ($row === null) {
            $changes[] = sprintf('%s "%s": create', $table, $identifier);
            if ($dryRun) {
                return null;
            }

            return $this->writer->create($table, ['pid' => 0, 'identifier' => $identifier] + $fields);
        }

        $uid = is_numeric($row['uid'] ?? null) ? (int)$row['uid'] : 0;
        $differences = self::changedFields($fields, $row);
        if ($differences !== []) {
            $changes[] = sprintf('%s "%s" (uid %d): update %s', $table, $identifier, $uid, implode(', ', array_keys($differences)));
            if (!$dryRun) {
                $this->writer->update($table, $uid, $differences);
            }
        }

        return $uid;
    }

    /**
     * Puts the records in the given order relative to each other. DataHandler
     * owns the sorting field (a new record goes to the top), so this moves a
     * record behind its predecessor only where the stored order differs.
     *
     * @param array<string, int|null> $uids identifier => uid, in the wanted order; null for a record a dry run would create
     * @param list<string> $changes
     */
    private function ensureOrder(string $table, array $uids, bool $dryRun, array &$changes): void
    {
        $wanted = array_values(array_filter($uids, is_int(...)));
        if (count($wanted) !== count($uids)) {
            $changes[] = sprintf('%s: order %s', $table, implode(', ', array_keys($uids)));

            return;
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $stored = $queryBuilder
            ->select('uid')
            ->from($table)
            ->where($queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($wanted, Connection::PARAM_INT_ARRAY)))
            ->orderBy('sorting')
            ->addOrderBy('uid')
            ->executeQuery()
            ->fetchFirstColumn();
        if (array_map('intval', $stored) === $wanted) {
            return;
        }
        $changes[] = sprintf('%s: order %s', $table, implode(', ', array_keys($uids)));
        if ($dryRun) {
            return;
        }
        $previous = null;
        foreach ($wanted as $uid) {
            if ($previous !== null) {
                $this->writer->moveAfter($table, $uid, $previous);
            }
            $previous = $uid;
        }
    }

    /**
     * The wanted fields whose stored value differs. Numbers compare as numbers
     * ("0.30" in a decimal column equals 0.3), everything else as text.
     *
     * @param array<string, int|float|string> $wanted
     * @param array<string, mixed> $stored
     * @return array<string, int|float|string>
     */
    public static function changedFields(array $wanted, array $stored): array
    {
        $differences = [];
        foreach ($wanted as $field => $value) {
            $current = $stored[$field] ?? null;
            if (is_int($value) || is_float($value)) {
                if (!is_numeric($current) || abs((float)$current - (float)$value) > 0.000001) {
                    $differences[$field] = $value;
                }
                continue;
            }
            if (!is_scalar($current) || (string)$current !== $value) {
                $differences[$field] = $value;
            }
        }

        return $differences;
    }

    /**
     * @return list<string>
     */
    private function ensureTools(bool $dryRun): array
    {
        $plan = $this->toolPolicy->plan(
            $this->toolStates->tools(),
            $this->toolStates->toolOverrides(),
            $this->toolStates->groupOverrides(),
        );
        $changes = [];
        foreach ($plan['groups'] as $group => $enabled) {
            $changes[] = sprintf('tool group %s: %s', $group, $enabled ? 'on' : 'off');
            if (!$dryRun) {
                $this->toolStates->setGroup($group, $enabled);
            }
        }
        foreach ($plan['tools'] as $tool => $enabled) {
            $changes[] = sprintf('tool %s: %s', $tool, $enabled ? 'on' : 'off');
            if (!$dryRun) {
                $this->toolStates->setTool($tool, $enabled);
            }
        }
        if ($plan['refused'] !== []) {
            // Not a change, a warning: the line repeats on every run until the
            // list in ToolPolicy is corrected.
            $changes[] = 'refused (no approval step any more, kept at its default): ' . implode(', ', $plan['refused']);
        }

        return $changes;
    }

    /**
     * Adds the Netresearch modules and the approval option to the editor
     * groups. Additive: nothing a group already has is removed.
     *
     * @return list<string>
     */
    private function ensureEditorGrants(bool $dryRun): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_groups');
        $queryBuilder->getRestrictions()->removeAll();
        $rows = $queryBuilder
            ->select('uid', 'title', 'groupMods', 'custom_options')
            ->from('be_groups')
            ->where(
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->in('title', $queryBuilder->createNamedParameter(self::EDITOR_GROUPS, Connection::PARAM_STR_ARRAY)),
            )
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();

        $changes = [];
        $found = [];
        foreach ($rows as $row) {
            $title = is_string($row['title'] ?? null) ? $row['title'] : '';
            $uid = is_numeric($row['uid'] ?? null) ? (int)$row['uid'] : 0;
            $found[] = $title;
            $update = [];
            $modules = self::withItems(is_string($row['groupMods'] ?? null) ? $row['groupMods'] : '', self::EDITOR_MODULES);
            if ($modules !== null) {
                $update['groupMods'] = $modules;
            }
            $options = self::withItems(is_string($row['custom_options'] ?? null) ? $row['custom_options'] : '', self::EDITOR_CUSTOM_OPTIONS);
            if ($options !== null) {
                $update['custom_options'] = $options;
            }
            if ($update === []) {
                continue;
            }
            $changes[] = sprintf('be_groups "%s" (uid %d): grant %s', $title, $uid, implode(', ', array_keys($update)));
            if (!$dryRun) {
                $this->writer->update('be_groups', $uid, $update);
            }
        }
        foreach (array_diff(self::EDITOR_GROUPS, $found) as $missing) {
            // A group is a rights decision; the seed never creates one.
            $changes[] = sprintf('be_groups "%s": not found, no grant (create the group in the backend if it is wanted)', $missing);
        }

        return $changes;
    }

    /**
     * The comma list with the missing items appended, or null when none is
     * missing.
     *
     * @param list<string> $items
     */
    public static function withItems(string $list, array $items): ?string
    {
        $current = array_values(array_filter(array_map(trim(...), explode(',', $list)), static fn(string $item): bool => $item !== ''));
        $missing = array_values(array_diff($items, $current));
        if ($missing === []) {
            return null;
        }

        return implode(',', array_merge($current, $missing));
    }

    private function uidByIdentifier(string $table, string $identifier): ?int
    {
        $row = $this->rowByIdentifier($table, $identifier);

        return $row !== null && is_numeric($row['uid'] ?? null) ? (int)$row['uid'] : null;
    }

    /**
     * The one live record with this identifier, hidden ones included (a hidden
     * record is shown again rather than duplicated).
     *
     * @return array<string, mixed>|null
     */
    private function rowByIdentifier(string $table, string $identifier): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $rows = $queryBuilder
            ->select('*')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq('identifier', $queryBuilder->createNamedParameter($identifier)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchAllAssociative();
        if (count($rows) > 1) {
            throw new \RuntimeException(sprintf('%d live %s records use the identifier "%s"; remove the extra ones first.', count($rows), $table, $identifier), 1795600012);
        }

        return $rows[0] ?? null;
    }
}
