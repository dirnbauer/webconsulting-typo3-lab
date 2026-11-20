<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Bootstrap;
use Webconsulting\SitePackage\AiStack\AiStackSeeder;

/**
 * Configures the Netresearch AI stack (nr-llm, nr-mcp-agent, t3-cowriter) the
 * same way locally and in production; see {@see AiStackSeeder}.
 *
 * Production runs it once per data run (docs/content/seeding.md). The
 * container start runs only --settings-only: settings.php is rebuilt from
 * settings.php.example on every deploy, and the chat needs the uid of its task
 * there.
 */
#[AsCommand(
    name: 'sitepackage:seed-ai-stack',
    description: 'Configure the Netresearch AI chat, the Cowriter tasks, the chat tools and the editor grants (idempotent).',
)]
final class SeedAiStackCommand extends Command
{
    public function __construct(private readonly AiStackSeeder $seeder)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List the changes without writing them.')
            ->addOption('settings-only', null, InputOption::VALUE_NONE, 'Only write the chat task uid (EXTENSIONS/nr_mcp_agent/llmTaskUid) into settings.php.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool)$input->getOption('dry-run');

        try {
            if ($input->getOption('settings-only')) {
                $changes = $this->seeder->synchronizeSettings($dryRun);
            } else {
                // DataHandler writes as the _cli_ administrator.
                Bootstrap::initializeBackendAuthentication();
                $changes = $this->seeder->seed($dryRun);
            }
        } catch (\RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        foreach ($changes as $change) {
            $io->writeln(($dryRun ? 'would ' : '') . '- ' . $change);
        }
        $io->success(sprintf('%d %s%s.', count($changes), count($changes) === 1 ? 'change' : 'changes', $dryRun ? ' planned (dry run, nothing written)' : ''));

        return Command::SUCCESS;
    }
}
