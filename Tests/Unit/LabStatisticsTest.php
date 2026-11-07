<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\ExpressionLanguage\FunctionsProvider\Typo3ConditionFunctionsProvider;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\TypoScript\AST\AstBuilder;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\Tokenizer\Line\InvalidLine;
use TYPO3\CMS\Core\TypoScript\Tokenizer\LosslessTokenizer;

final class LabStatisticsTest extends TestCase
{
    public function testNativeConditionExcludesEditorsMembersAndDevelopment(): void
    {
        $root = dirname(__DIR__, 2);
        $setup = file_get_contents($root . '/packages/site_package/Configuration/Sets/CookielessStatistics/setup.typoscript');
        self::assertIsString($setup);
        $condition = trim(explode("\n", $setup)[0], '[]');
        $language = new ExpressionLanguage(null, [new Typo3ConditionFunctionsProvider()]);
        foreach ([
            ['typo3-lab.webconsulting.at', false, false, true],
            ['typo3-lab.webconsulting.at', true, false, false],
            ['typo3-lab.webconsulting.at', false, true, false],
            ['typo3-lab.webconsulting.at', true, true, false],
            ['webconsulting-typo3-lab.ddev.site', false, false, false],
        ] as [$host, $backend, $frontend, $expected]) {
            $site = $this->createStub(Site::class);
            $site->method('getBase')->willReturn(new Uri('https://' . $host . '/'));
            self::assertSame($expected, $language->evaluate($condition, [
                'site' => $site,
                'backend' => (object)['user' => (object)['isLoggedIn' => $backend]],
                'frontend' => (object)['user' => (object)['isLoggedIn' => $frontend]],
            ]));
        }
        $tokenizer = new LosslessTokenizer();
        foreach ($tokenizer->tokenize($setup)->getNextLine() as $line) {
            self::assertNotInstanceOf(InvalidLine::class, $line);
        }
        $events = new class implements EventDispatcherInterface {
            public function dispatch(object $event): object { return $event; }
        };
        $ast = (new AstBuilder($events))->build($tokenizer->tokenize($setup), new RootNode())->toArray();
        self::assertSame('1', $ast['page.']['includeJSFooter.']['labStatistics.']['defer']);
        self::assertSame('EXT:site_package/Resources/Public/JavaScript/lab-statistics.js', $ast['page.']['includeJSFooter.']['labStatistics']);
    }

    public function testEveryDemoExplicitlyLoadsStatisticsAfterItsTheme(): void
    {
        $files = glob(dirname(__DIR__, 2) . '/config/sites/*/config.yaml');
        self::assertNotEmpty($files);
        foreach ($files as $file) {
            $config = Yaml::parseFile($file);
            $dependencies = $config['dependencies'];
            self::assertSame('webconsulting/lab-statistics', end($dependencies), $file);
            self::assertCount(1, array_keys($dependencies, 'webconsulting/lab-statistics', true), $file);
        }
    }
}
