<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Creates the two lab-owned AI manuals and keeps their screenshots current.
 *
 * Pages and content records are updated in place through DataHandler. Screenshot
 * files are imported into FAL so the manuals do not depend on Composer's hashed
 * public asset paths.
 */
#[AsCommand(
    name: 'sitepackage:seed-ai-manuals',
    description: 'Create or refresh the nr-llm and Cowriter frontend manuals.',
)]
final class SeedAiManualsCommand extends Command
{
    private const FEATURES_PAGE_UID = 1068;
    private const NR_LLM_SLUG = '/features/nr-llm-manual';
    private const COWRITER_SLUG = '/features/cowriter-manual';
    private const SCREENSHOT_DIRECTORY = 'EXT:site_package/Resources/Public/Images/AiManual/';
    private const FAL_FOLDER = 'ai-manual';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly StorageRepository $storageRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        Bootstrap::initializeBackendAuthentication();

        try {
            $nrLlmPageUid = $this->ensurePage(
                self::NR_LLM_SLUG,
                [
                    'title' => 'nr-llm manual — AI configuration in TYPO3',
                    'nav_title' => 'nr-llm manual',
                    'seo_title' => 'How to configure and use nr-llm in TYPO3',
                    'description' => 'How to set up providers, models and configurations in nr-llm 0.25, test them, and use AI features safely in the TYPO3 lab.',
                    'sorting' => 3584,
                ],
            );
            $cowriterPageUid = $this->ensurePage(
                self::COWRITER_SLUG,
                [
                    'title' => 'Cowriter manual — AI editing in TYPO3',
                    'nav_title' => 'Cowriter manual',
                    'seo_title' => 'How to configure and use the TYPO3 Cowriter',
                    'description' => 'How to check the t3-cowriter 3.5 setup, use its CKEditor toolbar, tasks and context scopes, and insert AI text into TYPO3 safely.',
                    'sorting' => 3840,
                ],
            );

            $this->seedNrLlmManual($nrLlmPageUid);
            $this->seedCowriterManual($cowriterPageUid);

            $io->success('The nr-llm and Cowriter manuals were created or refreshed.');
            $io->definitionList(
                ['nr-llm manual' => sprintf('%d (%s)', $nrLlmPageUid, self::NR_LLM_SLUG)],
                ['Cowriter manual' => sprintf('%d (%s)', $cowriterPageUid, self::COWRITER_SLUG)],
            );

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
    }

    private function seedNrLlmManual(int $pageUid): void
    {
        $headerUid = $this->ensureContent(
            $pageUid,
            'desiderio_headersection',
            'nr-llm: the shared AI foundation',
            [
                'eyebrow' => 'Administrator manual',
                'subheadline' => 'This manual covers nr-llm version 0.25.0. Set up providers once, keep API keys in nr-vault and give each TYPO3 AI feature a named configuration you can test.',
                'desiderio_headersection_variant' => 'center',
                'sorting' => 256,
            ],
        );
        $overviewUid = $this->ensureContent(
            $pageUid,
            'desiderio_textmedia',
            '1. Start in Administration → LLM',
            [
                'subheadline' => 'The dashboard is where you manage providers, models, configurations, tasks, costs, tools and diagnostics.',
                'content' => <<<'HTML'
<ol>
  <li>Open <strong>Administration → LLM</strong>.</li>
  <li>Check the OpenAI provider. It must be active, use the nr-vault identifier <code>openai_api_key</code>, have a 120-second API timeout and be marked as an external/global trust zone.</li>
  <li>Click <strong>Test connection</strong> after you change an endpoint or credential. A failed live health badge does not reveal the key, so check the provider test result and the TYPO3 logs.</li>
  <li>Check usage and costs before you switch the default model.</li>
</ol>
<p><strong>Never paste an API key into TypoScript, Page TSconfig, JavaScript, or page content.</strong> The provider record stores only the nr-vault identifier.</p>
HTML,
                'shadcn_layout' => 'media-right',
                'media_rounded' => 1,
                'media' => 1,
                'sorting' => 512,
            ],
        );
        $this->attachScreenshot(
            $overviewUid,
            $pageUid,
            'nr-llm-overview.jpg',
            'nr-llm dashboard in the TYPO3 backend',
            'The nr-llm dashboard in the TYPO3 backend with the status of providers, models, configurations, tasks, usage and costs.',
        );

        $configurationUid = $this->ensureContent(
            $pageUid,
            'desiderio_textmedia',
            '2. Choose named configurations, not raw models',
            [
                'subheadline' => 'A configuration puts the model, system prompt, temperature, limits and use-case behaviour behind one stable identifier.',
                'content' => <<<'HTML'
<ul>
  <li><code>content-assistant</code> is the active default and uses <strong>GPT-5.6 Terra</strong> for quality-first editorial work.</li>
  <li><code>content-assistant-fast</code> uses <strong>GPT-5.6 Luna</strong> for cheaper deterministic transformations.</li>
  <li><code>image-generation</code> uses <strong>GPT Image 2</strong>.</li>
  <li><code>backend-assistant</code> is kept separate for the backend agent.</li>
</ul>
<p>Open a row and click <strong>Test configuration</strong> before you assign it to a task. Keep exactly one active default configuration, so that generic <code>chat()</code> and <code>complete()</code> calls always resolve to the same one.</p>
<p>The old lab-only Responses API options are gone. GPT-5.6 Chat Completions now support function tools directly, and nr-llm 0.25 uses that path.</p>
HTML,
                'shadcn_layout' => 'media-left',
                'media_rounded' => 1,
                'media' => 1,
                'sorting' => 768,
            ],
        );
        $this->attachScreenshot(
            $configurationUid,
            $pageUid,
            'nr-llm-configurations.jpg',
            'Named nr-llm configurations in TYPO3',
            'The nr-llm configuration list in TYPO3, with Content Assistant on GPT-5.6 Terra as the active default.',
        );

        $checklistUid = $this->ensureContent(
            $pageUid,
            'text',
            '3. Daily use and troubleshooting',
            [
                'header_layout' => 2,
                'bodytext' => <<<'HTML'
<h3>Before you use an AI feature</h3>
<ol>
  <li>Check that the provider, the model and the configuration are active.</li>
  <li>Run the configuration test with a harmless prompt.</li>
  <li>Pick a named configuration that fits the task: Luna for simple transformations, Terra where the quality of the text matters.</li>
  <li>Treat model output as a draft. Check facts, links, accessibility, tone and personal data before you publish.</li>
</ol>
<h3>If a request fails</h3>
<ul>
  <li><strong>No default provider/configuration:</strong> mark one active configuration as the default.</li>
  <li><strong>401/403:</strong> test the provider and check the nr-vault secret identifier.</li>
  <li><strong>Timeout:</strong> for long generations, set the provider timeout to 120 seconds or more.</li>
  <li><strong>Tool unavailable:</strong> check that the model has the <code>tools</code> capability, then look at the global Tools module.</li>
  <li><strong>Unexpected model:</strong> check which configuration the task uses. A configuration assigned to a task takes precedence over the default.</li>
</ul>
HTML,
                'sorting' => 1024,
            ],
        );

        $this->orderRecords('tt_content', $pageUid, [$headerUid, $overviewUid, $configurationUid, $checklistUid]);
    }

    private function seedCowriterManual(int $pageUid): void
    {
        $headerUid = $this->ensureContent(
            $pageUid,
            'desiderio_headersection',
            'Cowriter: AI assistance inside CKEditor',
            [
                'eyebrow' => 'Editor manual',
                'subheadline' => 'This manual covers Cowriter version 3.5.0. Improve, summarise, structure, translate and review rich text without leaving the TYPO3 form or exposing API keys.',
                'desiderio_headersection_variant' => 'center',
                'sorting' => 256,
            ],
        );
        $statusUid = $this->ensureContent(
            $pageUid,
            'desiderio_textmedia',
            '1. Verify the setup before editing',
            [
                'subheadline' => 'Cowriter 3.5 has its own status module. It shows which nr-llm layer still needs attention.',
                'content' => <<<'HTML'
<ol>
  <li>Open <strong>Administration → Cowriter Status</strong>.</li>
  <li>Check that provider, API key, model, active configuration and default configuration all show green.</li>
  <li>If a check fails, use its fix link and return to this status page.</li>
</ol>
<p>The lab also registers a combined <strong>Desiderio + Cowriter</strong> RTE preset. That is why Cowriter appears in generic TYPO3 text fields and in the rich-text fields of Desiderio content elements.</p>
<p>For generic fields, the Page TSconfig default stays <code>RTE.default.preset = cowriter</code>. Desiderio fields set the <code>desiderio</code> preset explicitly, and the lab adds the Cowriter controls to it.</p>
HTML,
                'shadcn_layout' => 'media-right',
                'media_rounded' => 1,
                'media' => 1,
                'sorting' => 512,
            ],
        );
        $this->attachScreenshot(
            $statusUid,
            $pageUid,
            'cowriter-status.jpg',
            'Cowriter setup status in TYPO3',
            'The Cowriter Status backend module with passed checks for provider, model, API key and configuration.',
        );

        $dialogUid = $this->ensureContent(
            $pageUid,
            'desiderio_textmedia',
            '2. Run a task and review the result',
            [
                'subheadline' => 'The dialog has separate areas for the task, the context scope, optional references, your instructions and the result preview.',
                'content' => <<<'HTML'
<ol>
  <li>Open any content element with a rich-text field.</li>
  <li>Select text for a focused rewrite, or leave the selection empty to work with the full editor content.</li>
  <li>Click the <strong>Cowriter</strong> sparkle button.</li>
  <li>Choose a task and a context scope. Use page or parent-page context only when that extra content matters for the task.</li>
  <li>Optionally add instructions such as tone, audience, length or required terms.</li>
  <li>Click <strong>Execute</strong>, check the result, then choose <strong>Insert</strong>. Insert only changes the editor content, so save the TYPO3 record afterwards.</li>
</ol>
<p>Use <strong>Reset</strong> to refine a request without closing the editor, and <strong>Cancel</strong> to discard the result.</p>
HTML,
                'shadcn_layout' => 'media-left',
                'media_rounded' => 1,
                'media' => 1,
                'sorting' => 768,
            ],
        );
        $this->attachScreenshot(
            $dialogUid,
            $pageUid,
            'cowriter-dialog.jpg',
            'Cowriter task dialog in CKEditor',
            'The Cowriter dialog with the Improve Text task, full-content context, an instruction, the result and the insert controls.',
        );

        $referenceUid = $this->ensureContent(
            $pageUid,
            'text',
            '3. Tasks, shortcuts, and safe use',
            [
                'header_layout' => 2,
                'bodytext' => <<<'HTML'
<h3>Configured tasks</h3>
<p>The lab provides tasks that improve, summarise or extend text and fix its grammar. Other tasks translate to English or German, format text as a table, add structure, convert text to a list or enhance the visual layout.</p>
<p>Tasks live in <strong>Administration → LLM → Tasks</strong> and use the category <code>content</code>. Each task is assigned to either the Terra or the Luna configuration.</p>
<h3>Toolbar shortcuts</h3>
<ul>
  <li><strong>Cowriter:</strong> full task dialog with preview.</li>
  <li><strong>Vision:</strong> generate alt text for a selected editor image.</li>
  <li><strong>Translate:</strong> translate selected text inline.</li>
  <li><strong>Tasks:</strong> open the dialog with a predefined task selected.</li>
</ul>
<h3>Editorial guardrails</h3>
<ul>
  <li>Don't send confidential, personal or embargoed content unless the provider and your policy allow it.</li>
  <li>Use the smallest context scope that works. A larger scope costs more and can weaken the instruction.</li>
  <li>After inserting, check headings, links, lists, tables, facts, language and accessibility.</li>
  <li>If the toolbar is missing, reload the form, check the active RTE preset and open <strong>Cowriter Status</strong>.</li>
  <li>If the task list is empty, create active tasks with category <code>content</code> and reload the editor.</li>
</ul>
HTML,
                'sorting' => 1024,
            ],
        );

        $this->orderRecords('tt_content', $pageUid, [$headerUid, $statusUid, $dialogUid, $referenceUid]);
    }

    /**
     * @param array<string, int|string> $fields
     */
    private function ensurePage(string $slug, array $fields): int
    {
        $uid = $this->findPageBySlug($slug);
        $pageData = $fields + [
            'pid' => self::FEATURES_PAGE_UID,
            'slug' => $slug,
            'doktype' => 1,
            'hidden' => 0,
            'nav_hide' => 0,
            'sys_language_uid' => 0,
        ];

        if ($uid !== null) {
            $this->applyData('pages', $uid, $pageData);

            return $uid;
        }

        return $this->createRecord('pages', $pageData);
    }

    /**
     * @param array<string, int|string> $fields
     */
    private function ensureContent(int $pid, string $cType, string $header, array $fields): int
    {
        $connection = $this->connectionPool->getConnectionForTable('tt_content');
        $uid = $connection->select(
            ['uid'],
            'tt_content',
            [
                'pid' => $pid,
                'CType' => $cType,
                'header' => $header,
                'sys_language_uid' => 0,
                'deleted' => 0,
            ],
        )->fetchOne();
        $contentData = $fields + [
            'pid' => $pid,
            'CType' => $cType,
            'header' => $header,
            'colPos' => 0,
            'hidden' => 0,
            'sys_language_uid' => 0,
        ];

        if (is_numeric($uid)) {
            $contentUid = (int)$uid;
            $this->applyData('tt_content', $contentUid, $contentData);

            return $contentUid;
        }

        return $this->createRecord('tt_content', $contentData);
    }

    private function attachScreenshot(
        int $contentUid,
        int $pageUid,
        string $fileName,
        string $title,
        string $alternative,
    ): void {
        $sourcePath = GeneralUtility::getFileAbsFileName(self::SCREENSHOT_DIRECTORY . $fileName);
        if ($sourcePath === '' || !is_file($sourcePath)) {
            throw new \RuntimeException(sprintf('Manual screenshot "%s" is missing.', $fileName), 1785002401);
        }

        $storage = $this->storageRepository->getDefaultStorage();
        if ($storage === null) {
            throw new \RuntimeException('No default FAL storage is configured.', 1785002402);
        }
        $rootFolder = $storage->getRootLevelFolder(false);
        $folder = $rootFolder->hasFolder(self::FAL_FOLDER)
            ? $rootFolder->getSubfolder(self::FAL_FOLDER)
            : $rootFolder->createFolder(self::FAL_FOLDER);

        // TYPO3 14 verifies that the source MIME type and extension agree.
        // GeneralUtility::tempnam() has no suffix, so keep the real extension
        // on the import copy instead of presenting a PNG as an extensionless
        // temporary file.
        $temporaryBasePath = GeneralUtility::tempnam('ai_manual_');
        $temporaryPath = $temporaryBasePath . '.' . pathinfo($fileName, PATHINFO_EXTENSION);
        @unlink($temporaryBasePath);
        if (!copy($sourcePath, $temporaryPath)) {
            @unlink($temporaryPath);
            throw new \RuntimeException(sprintf('Could not prepare screenshot "%s" for FAL.', $fileName), 1785002403);
        }

        try {
            $file = $folder->getFile($fileName);
            if ($file instanceof FileInterface) {
                $file = $storage->replaceFile($file, $temporaryPath);
            } else {
                $file = $folder->addFile($temporaryPath, $fileName);
            }
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }

        $fileUidValue = $file->getProperty('uid');
        if (!is_int($fileUidValue) && !is_numeric($fileUidValue)) {
            throw new \RuntimeException(sprintf('Imported screenshot "%s" has no valid FAL uid.', $fileName), 1785002404);
        }
        $fileUid = (int)$fileUidValue;
        if ($fileUid <= 0) {
            throw new \RuntimeException(sprintf('Imported screenshot "%s" has no valid FAL uid.', $fileName), 1785002405);
        }

        $now = time();
        $metadataConnection = $this->connectionPool->getConnectionForTable('sys_file_metadata');
        $metadataUid = $metadataConnection->select(
            ['uid'],
            'sys_file_metadata',
            ['file' => $fileUid, 'sys_language_uid' => 0],
        )->fetchOne();
        $metadata = [
            'pid' => 0,
            'tstamp' => $now,
            'file' => $fileUid,
            'sys_language_uid' => 0,
            'title' => $title,
            'alternative' => $alternative,
            'description' => $alternative,
        ];
        if (is_numeric($metadataUid)) {
            $metadataConnection->update('sys_file_metadata', $metadata, ['uid' => (int)$metadataUid]);
        } else {
            $metadataConnection->insert('sys_file_metadata', $metadata + ['crdate' => $now]);
        }

        $referenceConnection = $this->connectionPool->getConnectionForTable('sys_file_reference');
        $referenceConnection->delete(
            'sys_file_reference',
            [
                'uid_foreign' => $contentUid,
                'tablenames' => 'tt_content',
                'fieldname' => 'media',
                'deleted' => 0,
            ],
        );
        $referenceConnection->insert(
            'sys_file_reference',
            [
                'pid' => $pageUid,
                'tstamp' => $now,
                'crdate' => $now,
                'hidden' => 0,
                'deleted' => 0,
                'sys_language_uid' => 0,
                'uid_local' => $fileUid,
                'uid_foreign' => $contentUid,
                'tablenames' => 'tt_content',
                'fieldname' => 'media',
                'sorting_foreign' => 1,
                'title' => $title,
                'alternative' => $alternative,
                'description' => $alternative,
            ],
        );
        $this->connectionPool->getConnectionForTable('tt_content')->update(
            'tt_content',
            ['media' => 1, 'tstamp' => $now],
            ['uid' => $contentUid],
        );
    }

    /**
     * @param list<int> $uids
     */
    private function orderRecords(string $table, int $parentUid, array $uids): void
    {
        $previousUid = null;
        foreach ($uids as $uid) {
            $target = $previousUid === null ? $parentUid : -$previousUid;
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start([], [$table => [$uid => ['move' => $target]]]);
            $dataHandler->process_cmdmap();
            if ($dataHandler->errorLog !== []) {
                throw new \RuntimeException(
                    'DataHandler ordering error: ' . $this->formatDataHandlerErrors($dataHandler),
                    1785002404,
                );
            }
            $previousUid = $uid;
        }
    }

    private function findPageBySlug(string $slug): ?int
    {
        $uid = $this->connectionPool->getConnectionForTable('pages')->select(
            ['uid'],
            'pages',
            ['slug' => $slug, 'sys_language_uid' => 0, 'deleted' => 0],
        )->fetchOne();

        return is_numeric($uid) ? (int)$uid : null;
    }

    /**
     * @param array<string, int|string> $fields
     */
    private function createRecord(string $table, array $fields): int
    {
        $newIdentifier = 'NEW' . bin2hex(random_bytes(8));
        $dataHandler = $this->runDataHandler([$table => [$newIdentifier => $fields]]);
        $uid = $dataHandler->substNEWwithIDs[$newIdentifier] ?? null;
        if (!is_numeric($uid)) {
            throw new \RuntimeException(sprintf('TYPO3 did not return a UID for new %s record.', $table), 1785002405);
        }

        return (int)$uid;
    }

    /**
     * @param array<string, int|string> $fields
     */
    private function applyData(string $table, int $uid, array $fields): void
    {
        $this->runDataHandler([$table => [$uid => $fields]]);
    }

    /**
     * @param array<string, array<int|string, array<string, int|string>>> $dataMap
     */
    private function runDataHandler(array $dataMap): DataHandler
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($dataMap, []);
        $dataHandler->process_datamap();
        if ($dataHandler->errorLog !== []) {
            throw new \RuntimeException(
                'DataHandler error: ' . $this->formatDataHandlerErrors($dataHandler),
                1785002406,
            );
        }

        return $dataHandler;
    }

    private function formatDataHandlerErrors(DataHandler $dataHandler): string
    {
        return implode('; ', array_map(
            static fn (mixed $error): string => is_string($error) ? $error : get_debug_type($error),
            $dataHandler->errorLog,
        ));
    }
}
