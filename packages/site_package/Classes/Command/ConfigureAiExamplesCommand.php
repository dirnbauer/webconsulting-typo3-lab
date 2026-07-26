<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Keeps the lab's nr-llm model routing and editor examples reproducible.
 *
 * Provider credentials stay in nr-vault. This command only copies the
 * provider's opaque vault identifier into the specialized OpenAI image
 * service configuration; it never reads or writes the underlying secret.
 */
#[AsCommand(
    name: 'sitepackage:configure-ai-examples',
    description: 'Configure GPT-5.6 Terra/Luna, GPT Image 2, Cowriter examples, and the backend assistant task.',
)]
final class ConfigureAiExamplesCommand extends Command
{
    private const CONTENT_SYSTEM_PROMPT = <<<'PROMPT'
You are a professional web content writer and editor. Preserve factual meaning and valid HTML. Follow the requested audience, tone, language, and structure. Return only the requested content without commentary unless the user explicitly asks for an explanation.
PROMPT;

    private const FAST_CONTENT_SYSTEM_PROMPT = <<<'PROMPT'
You are a precise web content editor for small, deterministic transformations. Preserve factual meaning and valid HTML. Change only what the instruction requests and return only the resulting content.
PROMPT;

    private const IMAGE_SYSTEM_PROMPT = <<<'PROMPT'
Create a production-ready, brand-safe website image. Prefer a clear focal point, useful negative space, coherent lighting, and no embedded text unless the prompt explicitly requests it.
PROMPT;

    private const BACKEND_CONFIGURATION_PROMPT = <<<'PROMPT'
You are a careful TYPO3 backend operations assistant. Give accurate, concise, editor-facing help and preserve the site's integrity.
PROMPT;

    private const BACKEND_ASSISTANT_PROMPT = <<<'PROMPT'
Use available tools when they can establish facts and treat tool results as authoritative. Explain material changes before making them, and ask for confirmation before destructive or irreversible actions.
PROMPT;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $provider = $this->findOpenAiProvider();
            if ($provider === null) {
                $io->error('No active OpenAI provider exists in tx_nrllm_provider. Configure it in Admin Tools > LLM first.');
                return Command::FAILURE;
            }

            $providerUid = (int)$provider['uid'];
            $terraUid = $this->ensureModel(
                'gpt-5-6-terra',
                [
                    'name' => 'GPT-5.6 Terra',
                    'description' => 'Balanced GPT-5.6 model for content, analysis, translation, coding, and agent workflows.',
                    'provider_uid' => $providerUid,
                    'model_id' => 'gpt-5.6-terra',
                    'context_length' => 1_050_000,
                    'max_output_tokens' => 128_000,
                    'capabilities' => 'chat,vision,streaming,tools,json_mode',
                    'default_timeout' => 180,
                    'cost_input' => 250,
                    'cost_output' => 1500,
                    'is_active' => 1,
                    'is_default' => 1,
                ],
            );
            $lunaUid = $this->ensureModel(
                'gpt-5-6-luna',
                [
                    'name' => 'GPT-5.6 Luna',
                    'description' => 'Cost-efficient GPT-5.6 model for short, high-volume, low-complexity transformations.',
                    'provider_uid' => $providerUid,
                    'model_id' => 'gpt-5.6-luna',
                    'context_length' => 1_050_000,
                    'max_output_tokens' => 128_000,
                    'capabilities' => 'chat,vision,streaming,tools,json_mode',
                    'default_timeout' => 120,
                    'cost_input' => 100,
                    'cost_output' => 600,
                    'is_active' => 1,
                    'is_default' => 0,
                ],
            );
            $imageUid = $this->ensureModel(
                'gpt-image-2',
                [
                    'name' => 'GPT Image 2',
                    'description' => 'OpenAI image generation and editing model with flexible image sizes.',
                    'provider_uid' => $providerUid,
                    'model_id' => 'gpt-image-2',
                    'context_length' => 0,
                    'max_output_tokens' => 0,
                    'capabilities' => 'image',
                    'default_timeout' => 300,
                    'cost_input' => 0,
                    'cost_output' => 0,
                    'is_active' => 1,
                    'is_default' => 0,
                ],
            );
            $this->makeDefault('tx_nrllm_model', $terraUid);

            $contentConfigurationUid = $this->ensureConfiguration(
                'content-assistant',
                [
                    'name' => 'Content Assistant',
                    'description' => 'Default quality-first content configuration using GPT-5.6 Terra.',
                    'model_uid' => $terraUid,
                    'system_prompt' => self::CONTENT_SYSTEM_PROMPT,
                    'max_tokens' => 8192,
                    'timeout' => 180,
                    'is_default' => 1,
                ],
            );
            $fastConfigurationUid = $this->ensureConfiguration(
                'content-assistant-fast',
                [
                    'name' => 'Content Assistant Fast',
                    'description' => 'Low-cost deterministic content transformations using GPT-5.6 Luna.',
                    'model_uid' => $lunaUid,
                    'system_prompt' => self::FAST_CONTENT_SYSTEM_PROMPT,
                    'max_tokens' => 4096,
                    'timeout' => 120,
                    'is_default' => 0,
                ],
            );
            $backendConfigurationUid = $this->ensureConfiguration(
                'backend-assistant',
                [
                    'name' => 'TYPO3 Backend Assistant',
                    'description' => 'Dedicated GPT-5.6 Terra configuration for nr-mcp-agent.',
                    'model_uid' => $terraUid,
                    'system_prompt' => self::BACKEND_CONFIGURATION_PROMPT,
                    'max_tokens' => 16_384,
                    'timeout' => 180,
                    'is_default' => 0,
                ],
            );
            $this->ensureConfiguration(
                'image-generation',
                [
                    'name' => 'Image Generation',
                    'description' => 'OpenAI Image API configuration using GPT Image 2.',
                    'model_uid' => $imageUid,
                    'system_prompt' => self::IMAGE_SYSTEM_PROMPT,
                    'max_tokens' => 4096,
                    'timeout' => 300,
                    'is_default' => 0,
                ],
            );
            $this->makeDefault('tx_nrllm_configuration', $contentConfigurationUid);

            $this->routeExistingConfigurations($terraUid, $lunaUid);
            $taskCount = $this->seedCowriterTasks($contentConfigurationUid, $fastConfigurationUid);
            $backendTaskUid = $this->ensureTask(
                'backend-assistant',
                [
                    'name' => 'TYPO3 Backend Assistant',
                    'description' => 'General TYPO3 backend agent prompt used by nr-mcp-agent.',
                    'category' => 'system',
                    'configuration_uid' => $backendConfigurationUid,
                    'prompt_template' => self::BACKEND_ASSISTANT_PROMPT,
                    'input_type' => 'manual',
                    'input_source' => '',
                    'output_format' => 'markdown',
                    'is_active' => 1,
                    'is_system' => 1,
                    'sorting' => 10,
                ],
            );
            $this->disableBrokenLegacyTask();
            $this->synchronizeExtensionSettings($provider, $backendTaskUid, $io);

            $io->success('AI models, Content Assistant, Cowriter examples, and image generation are configured.');
            $io->definitionList(
                ['Default content model' => 'gpt-5.6-terra'],
                ['Low-end model' => 'gpt-5.6-luna'],
                ['Image model' => 'gpt-image-2'],
                ['Cowriter tasks' => (string)$taskCount],
                ['nr-mcp-agent task UID' => (string)$backendTaskUid],
            );

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * @return array{uid: int, api_key: string}|null
     */
    private function findOpenAiProvider(): ?array
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_nrllm_provider');
        $queryBuilder = $connection->createQueryBuilder();
        $row = $queryBuilder
            ->select('uid', 'api_key')
            ->from('tx_nrllm_provider')
            ->where(
                $queryBuilder->expr()->eq('adapter_type', $queryBuilder->createNamedParameter('openai')),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0)),
                $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(0)),
                $queryBuilder->expr()->eq('is_active', $queryBuilder->createNamedParameter(1)),
            )
            ->orderBy('priority', 'DESC')
            ->addOrderBy('sorting', 'ASC')
            ->addOrderBy('uid', 'ASC')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        if ($row === false || !is_numeric($row['uid'] ?? null)) {
            return null;
        }

        return [
            'uid' => (int)$row['uid'],
            'api_key' => is_string($row['api_key'] ?? null) ? $row['api_key'] : '',
        ];
    }

    /**
     * @param array<string, int|string> $fields
     */
    private function ensureModel(string $identifier, array $fields): int
    {
        return $this->upsertByIdentifier('tx_nrllm_model', $identifier, $fields + ['sorting' => 0]);
    }

    /**
     * @param array<string, int|string> $fields
     */
    private function ensureConfiguration(string $identifier, array $fields): int
    {
        return $this->upsertByIdentifier(
            'tx_nrllm_configuration',
            $identifier,
            $fields + [
                'model_selection_mode' => 'fixed',
                'temperature' => '1.00',
                'top_p' => '1.00',
                'frequency_penalty' => '0.00',
                'presence_penalty' => '0.00',
                'options' => '',
                'is_active' => 1,
                'allowed_tool_groups' => '',
                'sorting' => 0,
            ],
        );
    }

    /**
     * @param array<string, int|string> $fields
     */
    private function ensureTask(string $identifier, array $fields): int
    {
        return $this->upsertByIdentifier('tx_nrllm_task', $identifier, $fields);
    }

    /**
     * @param array<string, int|string> $fields
     */
    private function upsertByIdentifier(string $table, string $identifier, array $fields): int
    {
        $connection = $this->connectionPool->getConnectionForTable($table);
        $uids = $connection->select(
            ['uid'],
            $table,
            ['identifier' => $identifier, 'deleted' => 0],
        )->fetchFirstColumn();
        if (count($uids) > 1) {
            throw new \RuntimeException(sprintf('Multiple active %s records use identifier "%s".', $table, $identifier));
        }
        $uid = $uids[0] ?? null;
        $now = time();
        $data = $fields + [
            'pid' => 0,
            'hidden' => 0,
            'deleted' => 0,
        ];
        $data['identifier'] = $identifier;
        $data['tstamp'] = $now;

        if (is_numeric($uid)) {
            $recordUid = (int)$uid;
            $connection->update($table, $data, ['uid' => $recordUid]);
            return $recordUid;
        }

        $data['crdate'] = $now;
        $connection->insert($table, $data);
        return (int)$connection->lastInsertId();
    }

    private function makeDefault(string $table, int $recordUid): void
    {
        $connection = $this->connectionPool->getConnectionForTable($table);
        $now = time();
        $connection->update($table, ['is_default' => 0, 'tstamp' => $now], ['deleted' => 0]);
        $connection->update($table, ['is_default' => 1, 'tstamp' => $now], ['uid' => $recordUid, 'deleted' => 0]);
    }

    private function routeExistingConfigurations(int $terraUid, int $lunaUid): void
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_nrllm_configuration');
        foreach ([
            'content-summarizer',
            'translator',
            'seo-optimizer',
            'code-assistant',
            'default',
            'best-pages-analysis-config',
        ] as $identifier) {
            $connection->update(
                'tx_nrllm_configuration',
                ['model_uid' => $terraUid, 'temperature' => '1.00', 'top_p' => '1.00', 'options' => '', 'tstamp' => time()],
                ['identifier' => $identifier, 'deleted' => 0],
            );
        }
        $connection->update(
            'tx_nrllm_configuration',
            ['model_uid' => $lunaUid, 'temperature' => '1.00', 'top_p' => '1.00', 'options' => '', 'tstamp' => time()],
            ['identifier' => 'solr-search-query-enhancer', 'deleted' => 0],
        );
    }

    private function seedCowriterTasks(int $terraConfigurationUid, int $lunaConfigurationUid): int
    {
        foreach ($this->cowriterTasks() as $task) {
            $configurationUid = $task['tier'] === 'luna' ? $lunaConfigurationUid : $terraConfigurationUid;
            unset($task['tier']);
            $identifier = $task['identifier'];
            unset($task['identifier']);
            $task['prompt_template'] = str_replace('\\n', "\n", $task['prompt_template']);
            $this->ensureTask($identifier, $task + [
                'category' => 'content',
                'configuration_uid' => $configurationUid,
                'input_type' => 'manual',
                'input_source' => '',
                'output_format' => 'plain',
                'is_active' => 1,
                'is_system' => 1,
            ]);
        }

        return count($this->cowriterTasks());
    }

    /**
     * @return list<array{identifier: string, name: string, description: string, prompt_template: string, sorting: int, tier: 'terra'|'luna'}>
     */
    private function cowriterTasks(): array
    {
        return [
            [
                'identifier' => 'cowriter_improve',
                'name' => 'Improve Text',
                'description' => 'Enhance readability, clarity, and quality while preserving the original meaning.',
                'prompt_template' => 'Improve the following HTML content from a rich text editor. Enhance readability, clarity, and overall quality while preserving the original meaning and structure. Respond with ONLY the improved HTML, without explanations, commentary, or markdown fences.\n\n{{input}}',
                'sorting' => 10,
                'tier' => 'terra',
            ],
            [
                'identifier' => 'cowriter_summarize',
                'name' => 'Summarize',
                'description' => 'Create a concise summary of the text.',
                'prompt_template' => 'Summarize the following HTML content. Capture the key points concisely and preserve valid HTML. Respond with ONLY the summary as HTML, without explanations, commentary, or markdown fences.\n\n{{input}}',
                'sorting' => 20,
                'tier' => 'terra',
            ],
            [
                'identifier' => 'cowriter_extend',
                'name' => 'Extend / Elaborate',
                'description' => 'Add depth, detail, and supporting information to the text.',
                'prompt_template' => 'Expand the following HTML content. Add useful depth, detail, and examples while matching the existing tone and structure. Respond with ONLY the extended HTML, without explanations, commentary, or markdown fences.\n\n{{input}}',
                'sorting' => 30,
                'tier' => 'terra',
            ],
            [
                'identifier' => 'cowriter_fix_grammar',
                'name' => 'Fix Grammar & Spelling',
                'description' => 'Correct grammar, spelling, and punctuation with minimal changes.',
                'prompt_template' => 'Fix grammar, spelling, and punctuation in the following HTML. Make minimal changes, preserve the HTML structure exactly, and respond with ONLY the corrected HTML.\n\n{{input}}',
                'sorting' => 40,
                'tier' => 'luna',
            ],
            [
                'identifier' => 'cowriter_translate_en',
                'name' => 'Translate to English',
                'description' => 'Translate the text to English while preserving tone and markup.',
                'prompt_template' => 'Translate the following HTML content to English. Preserve meaning, tone, links, and HTML structure. Respond with ONLY the translated HTML.\n\n{{input}}',
                'sorting' => 50,
                'tier' => 'terra',
            ],
            [
                'identifier' => 'cowriter_translate_de',
                'name' => 'Translate to German',
                'description' => 'Translate the text to German while preserving tone and markup.',
                'prompt_template' => 'Translate the following HTML content to German. Preserve meaning, tone, links, and HTML structure. Respond with ONLY the translated HTML.\n\n{{input}}',
                'sorting' => 60,
                'tier' => 'terra',
            ],
            [
                'identifier' => 'cowriter_format_table',
                'name' => 'Format as Table',
                'description' => 'Convert structured information into an accessible HTML table.',
                'prompt_template' => 'Convert suitable data or comparisons in the following HTML into an accessible table using caption, thead, tbody, th, and td where appropriate. Keep non-tabular surrounding text. Respond with ONLY the resulting HTML.\n\n{{input}}',
                'sorting' => 70,
                'tier' => 'luna',
            ],
            [
                'identifier' => 'cowriter_add_structure',
                'name' => 'Add Structure',
                'description' => 'Organize unstructured text with headings, lists, and emphasis.',
                'prompt_template' => 'Add useful structure to the following HTML with headings, paragraphs, lists, and restrained emphasis. Preserve the meaning and respond with ONLY the structured HTML.\n\n{{input}}',
                'sorting' => 80,
                'tier' => 'terra',
            ],
            [
                'identifier' => 'cowriter_convert_list',
                'name' => 'Convert to List',
                'description' => 'Transform suitable prose into organized lists.',
                'prompt_template' => 'Convert suitable parts of the following HTML into ordered or unordered lists. Preserve meaning and respond with ONLY the resulting HTML.\n\n{{input}}',
                'sorting' => 90,
                'tier' => 'luna',
            ],
            [
                'identifier' => 'cowriter_visual_layout',
                'name' => 'Enhance Visual Layout',
                'description' => 'Improve scanability with a complete but restrained HTML layout pass.',
                'prompt_template' => 'Improve the visual hierarchy of the following HTML using headings, lists, tables, blockquotes, and restrained emphasis where appropriate. Preserve meaning and accessibility. Respond with ONLY the enhanced HTML.\n\n{{input}}',
                'sorting' => 100,
                'tier' => 'terra',
            ],
        ];
    }

    private function disableBrokenLegacyTask(): void
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_nrllm_task');
        $connection->update(
            'tx_nrllm_task',
            ['is_active' => 0, 'hidden' => 1, 'tstamp' => time()],
            ['identifier' => 'task_for_rte', 'deleted' => 0],
        );
    }

    /**
     * @param array{uid: int, api_key: string} $provider
     */
    private function synchronizeExtensionSettings(array $provider, int $backendTaskUid, SymfonyStyle $io): void
    {
        $nrLlm = (array)$this->extensionConfiguration->get('nr_llm');
        $nrLlm['image'] = is_array($nrLlm['image'] ?? null) ? $nrLlm['image'] : [];
        $nrLlm['image']['dalle'] = is_array($nrLlm['image']['dalle'] ?? null) ? $nrLlm['image']['dalle'] : [];
        unset($nrLlm['image']['dalle']['defaultModel']);
        $nrLlm['image']['dalle']['timeout'] = '300';
        $nrLlm['providers'] = is_array($nrLlm['providers'] ?? null) ? $nrLlm['providers'] : [];
        $nrLlm['providers']['openai'] = is_array($nrLlm['providers']['openai'] ?? null) ? $nrLlm['providers']['openai'] : [];

        $apiKeyIdentifier = is_string($provider['api_key'] ?? null) ? trim($provider['api_key']) : '';
        if ($apiKeyIdentifier !== '') {
            $nrLlm['providers']['openai']['apiKeyIdentifier'] = $apiKeyIdentifier;
        } else {
            $io->warning('The OpenAI provider has no nr-vault API-key identifier; image generation remains unavailable until one is configured.');
        }
        $this->extensionConfiguration->set('nr_llm', $nrLlm);

        $agent = (array)$this->extensionConfiguration->get('nr_mcp_agent');
        $agent['llmTaskUid'] = (string)$backendTaskUid;
        $this->extensionConfiguration->set('nr_mcp_agent', $agent);
    }
}
