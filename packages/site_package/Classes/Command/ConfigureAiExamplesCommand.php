<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Command;

use Doctrine\DBAL\ParameterType;
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
    description: 'Configure GPT-5.6 Terra/Luna, GPT-5 mini for tools, GPT Image 2, and the AI examples.',
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
            $toolModelUid = $this->ensureModel(
                'gpt-5-mini',
                [
                    'name' => 'GPT-5 mini',
                    'description' => 'Tool-capable OpenAI model for nr-llm agent workflows using Chat Completions.',
                    'provider_uid' => $providerUid,
                    'model_id' => 'gpt-5-mini',
                    'context_length' => 128_000,
                    'max_output_tokens' => 32_000,
                    'capabilities' => 'chat,vision,streaming,tools',
                    'default_timeout' => 120,
                    'cost_input' => 30,
                    'cost_output' => 120,
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
            $this->ensureConfiguration(
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
                    'description' => 'Shared tool-compatible GPT-5 mini configuration for nr-llm backend workflows.',
                    'model_uid' => $toolModelUid,
                    'system_prompt' => self::BACKEND_CONFIGURATION_PROMPT,
                    'max_tokens' => 16_384,
                    'timeout' => 120,
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
            $this->retireCowriterTasks();
            $backendTaskUid = $this->ensureTask(
                'backend-assistant',
                [
                    'name' => 'TYPO3 Backend Assistant',
                    'description' => 'General TYPO3 backend assistant prompt for nr-llm workflows.',
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
            $this->synchronizeExtensionSettings($provider, $io);

            $io->success('AI models, Content Assistant and image generation are configured.');
            $io->definitionList(
                ['Default content model' => 'gpt-5.6-terra'],
                ['Low-end model' => 'gpt-5.6-luna'],
                ['Tool model' => 'gpt-5-mini'],
                ['Image model' => 'gpt-image-2'],
                ['Backend assistant task UID' => (string)$backendTaskUid],
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

    /**
     * The lab no longer ships t3-cowriter (2026-09-26); earlier runs of this
     * command created its ten tasks, which nothing uses any more.
     */
    private function retireCowriterTasks(): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_nrllm_task');
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder
            ->update('tx_nrllm_task')
            ->set('deleted', 1)
            ->set('tstamp', time())
            ->where(
                $queryBuilder->expr()->like('identifier', $queryBuilder->createNamedParameter('cowriter\\_%')),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
            )
            ->executeStatement();
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
    private function synchronizeExtensionSettings(array $provider, SymfonyStyle $io): void
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
    }
}
