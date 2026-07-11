<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Command;

use Netresearch\NrLlm\Specialized\Image\DallEImageService;
use Netresearch\NrLlm\Specialized\Option\ImageGenerationOptions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Environment;

#[AsCommand(
    name: 'sitepackage:llm:generate-image',
    description: 'Generate an example image with nr-llm and OpenAI GPT Image 2.',
)]
final class GenerateLlmImageExampleCommand extends Command
{
    public function __construct(private readonly DallEImageService $imageService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('prompt', InputArgument::REQUIRED, 'Description of the image to generate.')
            ->addOption('size', null, InputOption::VALUE_REQUIRED, 'Image dimensions supported by gpt-image-2.', '1024x1024')
            ->addOption('configuration', null, InputOption::VALUE_REQUIRED, 'nr-llm configuration identifier.', 'image-generation')
            ->addOption('model', null, InputOption::VALUE_REQUIRED, 'Explicit model override; defaults to the configuration model.')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output PNG path, relative to the project root or absolute.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $prompt = trim($this->stringValue($input->getArgument('prompt')));
        $size = trim($this->stringValue($input->getOption('size')));
        $configuration = trim($this->stringValue($input->getOption('configuration')));
        $modelOverride = trim($this->stringValue($input->getOption('model')));

        if ($prompt === '') {
            $io->error('The image prompt must not be empty.');
            return Command::INVALID;
        }

        try {
            $model = $modelOverride !== ''
                ? $modelOverride
                : $this->imageService->resolveModelForConfiguration($configuration, 'gpt-image-2');
            $systemPrompt = $this->imageService->getConfigurationSystemPrompt($configuration);
            $effectivePrompt = trim($systemPrompt . ($systemPrompt !== '' ? "\n\n" : '') . $prompt);
            $path = $this->resolveOutputPath($this->stringValue($input->getOption('output')), $model);
            $directory = dirname($path);
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new \RuntimeException(sprintf('Could not create output directory "%s".', $directory));
            }
            if (file_exists($path)) {
                throw new \RuntimeException(sprintf('Output file "%s" already exists.', $path));
            }
            if (!is_writable($directory)) {
                throw new \RuntimeException(sprintf('Output directory "%s" is not writable.', $directory));
            }

            $result = $this->imageService->generate(
                $effectivePrompt,
                new ImageGenerationOptions(
                    model: $model,
                    size: $size,
                    quality: null,
                    style: null,
                    format: null,
                    configuration: $configuration !== '' ? $configuration : null,
                ),
            );

            if (file_exists($path)) {
                throw new \RuntimeException(sprintf('Output file "%s" already exists.', $path));
            }
            if (!$result->saveToFile($path)) {
                throw new \RuntimeException('The generated image could not be written to disk.');
            }

            $io->success('Image generated.');
            $io->definitionList(
                ['Model' => $result->model],
                ['Size' => $result->size],
                ['File' => $path],
            );
            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }
    }

    private function resolveOutputPath(string $configuredPath, string $model): string
    {
        $configuredPath = trim($configuredPath);
        if ($configuredPath === '') {
            $safeModel = preg_replace('/[^a-z0-9._-]+/i', '-', $model) ?: 'gpt-image-2';
            return Environment::getVarPath() . '/generated-images/' . $safeModel . '-' . date('Ymd-His') . '.png';
        }

        if (str_starts_with($configuredPath, '/')) {
            return $configuredPath;
        }

        return Environment::getProjectPath() . '/' . ltrim($configuredPath, '/');
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
