<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Releases the Netresearch AI Chat turns (EXT:nr_mcp_agent) that a container
 * stop or an OOM kill interrupted.
 *
 * A running turn holds its conversation in `locked` (or `tool_loop`). The
 * worker releases it when the turn ends, but in the production container a
 * stop never reaches the worker: Apache is PID 1, and when it exits the kernel
 * kills every other process. The conversation then stays claimed, the chat
 * answers "conversation is processing" for good, and the row counts against
 * the user's active-turn limit. The extension's ai-chat:cleanup releases such
 * rows after five minutes, but it also archives and deletes conversations,
 * which production keeps switched off (TYPO3_CHAT_CLEANUP).
 *
 * This command does the release alone. docker/coolify/entrypoint.sh runs it at
 * start, before any worker exists, with --before set to the moment the
 * container started: a turn last touched before that moment belongs to a
 * worker of the previous container, which is gone. Queued turns (`processing`)
 * are left alone; the new workers pick them up. The row becomes `failed`, as
 * the extension's own timeout makes it, so the chat offers a Retry button; the
 * approval fields go with the status, for the reason the extension gives (a
 * decision left on a failed row would be carried out by the Retry).
 */
#[AsCommand(
    name: 'sitepackage:ai-chat:release-interrupted',
    description: 'Mark AI chat turns interrupted by a restart as failed (locked/tool_loop rows last changed before --before).',
)]
final class ReleaseInterruptedChatTurnsCommand extends Command
{
    private const TABLE = 'tx_nrmcpagent_conversation';

    /** Statuses of a turn that a worker is executing. */
    private const RUNNING = ['locked', 'tool_loop'];

    public const MESSAGE = 'Interrupted by a restart of the server. Please try again.';

    public function __construct(private readonly ConnectionPool $connectionPool)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'before',
            null,
            InputOption::VALUE_REQUIRED,
            'Unix time: only turns last changed before it are released (default: now). The entrypoint passes the container start.',
        );
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count the turns that would be released, change nothing.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $before = self::before($input->getOption('before'));
        if ($before === null) {
            $io->error('--before must be a Unix time (seconds).');
            return Command::INVALID;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $where = [
            $queryBuilder->expr()->in('status', $queryBuilder->createNamedParameter(self::RUNNING, Connection::PARAM_STR_ARRAY)),
            $queryBuilder->expr()->lt('tstamp', $queryBuilder->createNamedParameter($before, Connection::PARAM_INT)),
            $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
        ];

        if ($input->getOption('dry-run')) {
            $count = $queryBuilder->count('uid')->from(self::TABLE)->where(...$where)->executeQuery()->fetchOne();
            $io->writeln(sprintf('%d interrupted chat turn(s) would be released (dry run).', is_numeric($count) ? (int)$count : 0));
            return Command::SUCCESS;
        }

        $released = $queryBuilder
            ->update(self::TABLE)
            ->set('status', 'failed')
            ->set('error_message', self::MESSAGE)
            ->set('error_code', '')
            ->set('approval_run_uuid', '')
            ->set('approval_decision', '')
            ->set('approval_turn_digest', '')
            ->where(...$where)
            ->executeStatement();

        $io->writeln(sprintf('%d interrupted chat turn(s) released.', $released));

        return Command::SUCCESS;
    }

    private static function before(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return time();
        }
        if (!is_string($value) || !ctype_digit($value)) {
            return null;
        }

        return (int)$value;
    }
}
