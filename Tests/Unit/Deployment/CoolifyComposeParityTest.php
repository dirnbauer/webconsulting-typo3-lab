<?php

declare(strict_types=1);

namespace Webconsulting\Lab\Tests\Unit\Deployment;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class CoolifyComposeParityTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
    }

    public function testRegistryComposeKeepsExactRuntimeParityWithoutBuildBlocks(): void
    {
        $source = Yaml::parseFile($this->root . '/docker-compose.coolify.yml');
        $registry = Yaml::parseFile($this->root . '/docker-compose.coolify.registry.yml');

        self::assertIsArray($source);
        self::assertIsArray($registry);
        self::assertSame($source['volumes'], $registry['volumes']);
        self::assertSame(array_keys($source['services']), array_keys($registry['services']));
        self::assertSame($source['services']['database'], $registry['services']['database']);

        foreach (['web', 'typo3-solr'] as $service) {
            self::assertArrayNotHasKey('build', $registry['services'][$service]);
            self::assertSame('always', $registry['services'][$service]['pull_policy'] ?? null);
            self::assertIsString($registry['services'][$service]['image'] ?? null);
            self::assertStringContainsString(
                'gitlab.webconsulting.at:5050/extensions/webconsulting-typo3-lab/',
                $registry['services'][$service]['image'],
            );
            self::assertStringContainsString(
                '${SOURCE_COMMIT:-latest}',
                $registry['services'][$service]['image'],
            );

            $sourceRuntime = $source['services'][$service];
            unset($sourceRuntime['build']);
            $registryRuntime = $registry['services'][$service];
            unset($registryRuntime['image'], $registryRuntime['pull_policy']);

            self::assertSame(
                $sourceRuntime,
                $registryRuntime,
                sprintf('Runtime configuration of service "%s" drifted between source and registry Compose files.', $service),
            );
        }
    }

    public function testGitLabPipelineBuildsWithSecretMountAndGatesDeployOnBothImages(): void
    {
        $pipeline = Yaml::parseFile($this->root . '/.gitlab-ci.yml');

        self::assertIsArray($pipeline);
        self::assertSame(['quality', 'build', 'deploy'], $pipeline['stages'] ?? null);
        self::assertArrayHasKey('build-web', $pipeline);
        self::assertArrayHasKey('build-solr', $pipeline);
        self::assertArrayHasKey('deploy-coolify', $pipeline);

        $webScript = implode("\n", $pipeline['build-web']['script'] ?? []);
        self::assertStringContainsString('--secret id=GITLAB_EXTENSIONS_READ_KEY,', $webScript);
        self::assertStringNotContainsString('build-arg GITLAB_EXTENSIONS_READ_KEY', $webScript);
        self::assertStringContainsString('--cache-to "type=registry,ref=${WEB_IMAGE_REPO}:buildcache,mode=max"', $webScript);

        $solrScript = implode("\n", $pipeline['build-solr']['script'] ?? []);
        self::assertStringContainsString('--file Dockerfile.solr.coolify', $solrScript);
        self::assertStringContainsString('--cache-to "type=registry,ref=${SOLR_IMAGE_REPO}:buildcache,mode=max"', $solrScript);

        self::assertSame(
            [['job' => 'build-web'], ['job' => 'build-solr']],
            $pipeline['deploy-coolify']['needs'] ?? null,
        );
    }
}
