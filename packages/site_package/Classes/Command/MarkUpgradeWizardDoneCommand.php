<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Attribute\UpgradeWizard;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;

/**
 * Marks upgrade wizards as done without running them: the counterpart of the
 * core's upgrade:mark:undone, which TYPO3 has no command for.
 *
 * For a wizard that must never run on this installation, such as nr-llm's
 * nrllmAdr214SkillVersionDigest, which disables every skill whose stored
 * frontmatter does not verify. netresearch/typo3-demo marks it done the same
 * way. The mark is the registry row TYPO3 writes for a wizard that ran
 * (namespace installUpdate, key = the wizard's class), set through the public
 * Registry API; a wizard already marked is left alone.
 */
#[AsCommand(
    name: 'sitepackage:upgrade:mark-done',
    description: 'Mark upgrade wizards as done without running them (by class name).',
)]
final class MarkUpgradeWizardDoneCommand extends Command
{
    public function __construct(private readonly Registry $registry)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('wizards', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Fully qualified class names of the wizards');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $wizards = $input->getArgument('wizards');
        $status = Command::SUCCESS;
        foreach (is_array($wizards) ? $wizards : [] as $className) {
            $className = ltrim((string)$className, '\\');
            if (!class_exists($className) || !is_subclass_of($className, UpgradeWizardInterface::class)) {
                $io->error(sprintf('%s is not an upgrade wizard class of this installation.', $className));
                $status = Command::FAILURE;
                continue;
            }
            $identifier = self::identifier($className);
            if ($this->registry->get('installUpdate', $className, false)) {
                $io->writeln(sprintf('%s (%s) is already marked as done.', $identifier, $className));
                continue;
            }
            $this->registry->set('installUpdate', $className, 1);
            $io->writeln(sprintf('%s (%s) marked as done, not run.', $identifier, $className));
        }

        return $status;
    }

    /**
     * @param class-string $className
     */
    private static function identifier(string $className): string
    {
        // IS_INSTANCEOF also matches the attribute written with its old name,
        // TYPO3\CMS\Install\Attribute\UpgradeWizard (an alias), as nr-llm does.
        foreach ((new \ReflectionClass($className))->getAttributes(UpgradeWizard::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            return $attribute->newInstance()->identifier;
        }

        return $className;
    }
}
