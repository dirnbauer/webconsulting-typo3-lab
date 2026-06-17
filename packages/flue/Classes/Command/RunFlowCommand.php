<?php

declare(strict_types=1);

namespace Webconsulting\Flue\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webconsulting\Flue\Service\FlowTriggerService;
use Webconsulting\Flue\Service\RunStore;
use Webconsulting\Flue\Support\Typed;

#[AsCommand(name: 'flue:run', description: 'Trigger a Flue flow on a page and drain the durable run to completion.')]
final class RunFlowCommand extends Command
{
    public function __construct(
        private readonly FlowTriggerService $flowTriggerService,
        private readonly RunStore $runStore,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('flow', InputArgument::REQUIRED, 'Flow uid (tx_flue_flow)');
        $this->addArgument('page', InputArgument::REQUIRED, 'Target page uid');
        $this->addOption('instructions', 'i', InputOption::VALUE_REQUIRED, 'Per-run instructions (supports {uid} {table} {title} {pid} {workspace})', '');
        $this->addOption('beuser', 'b', InputOption::VALUE_REQUIRED, 'Backend user uid used to mint the MCP token', '1');
        $this->addOption('workspace', 'w', InputOption::VALUE_REQUIRED, 'Workspace uid', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $flow = Typed::int($input->getArgument('flow'));
        $page = Typed::int($input->getArgument('page'));
        $instructions = Typed::string($input->getOption('instructions'));
        $beUser = Typed::int($input->getOption('beuser'));
        $workspace = Typed::int($input->getOption('workspace'));

        try {
            $result = $this->flowTriggerService->trigger($flow, 'pages', $page, $workspace, $instructions, $beUser);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->writeln(sprintf('Triggered run #%d (key %s, status %s) — draining…', $result['runUid'], $result['runKey'], $result['status']));
        $this->flowTriggerService->drainRun($result['runUid']);

        $run = $this->runStore->load($result['runUid']);
        if ($run === null) {
            $io->error('Run vanished.');
            return Command::FAILURE;
        }

        $io->section('Status: ' . $run->status->value);
        if ($run->output !== '') {
            $io->writeln($run->output);
        }
        if ($run->errorMessage !== '') {
            $io->warning($run->errorMessage);
        }

        return $run->status->value === 'settled' ? Command::SUCCESS : Command::FAILURE;
    }
}
