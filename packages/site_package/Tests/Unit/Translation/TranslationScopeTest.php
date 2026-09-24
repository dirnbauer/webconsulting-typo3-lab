<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Translation;

use PHPUnit\Framework\TestCase;
use Webconsulting\SitePackage\Translation\TranslationScope;

final class TranslationScopeTest extends TestCase
{
    private function scope(): TranslationScope
    {
        return TranslationScope::fromArray([
            'skip' => [
                ['page' => 737, 'owner' => 'utility translations'],
                ['page' => 736, 'languages' => ['de']],
                ['tree' => 712, 'languages' => ['de']],
                ['page' => 'not a uid'],
            ],
            'recordTables' => ['tx_news_domain_model_news'],
            'ignoreFields' => ['tx_powermail_domain_model_field' => ['marker', 'css']],
            'lineFields' => ['tx_powermail_domain_model_field' => ['settings' => '']],
        ]);
    }

    public function testSkipsAPageInEveryLanguage(): void
    {
        self::assertTrue($this->scope()->skips([737, 505], 'zh'));
        self::assertFalse($this->scope()->skips([738, 505], 'zh'));
    }

    public function testSkipsAPageOnlyInTheListedLanguages(): void
    {
        self::assertTrue($this->scope()->skips([736, 505], 'de'));
        self::assertFalse($this->scope()->skips([736, 505], 'hu'));
    }

    public function testATreeRuleCoversEveryDescendant(): void
    {
        self::assertTrue($this->scope()->skips([712, 505], 'de'));
        self::assertTrue($this->scope()->skips([792, 720, 712, 505], 'de'));
        self::assertFalse($this->scope()->skips([792, 720, 712, 505], 'zh'));
    }

    public function testIgnoresRulesWithoutAPage(): void
    {
        self::assertCount(3, $this->scope()->skip);
    }

    public function testKnowsIgnoredAndLineFields(): void
    {
        $scope = $this->scope();

        self::assertTrue($scope->ignores('tx_powermail_domain_model_field', 'marker'));
        self::assertFalse($scope->ignores('tx_powermail_domain_model_field', 'title'));
        self::assertSame('|', $scope->lineSeparator('tx_powermail_domain_model_field', 'settings'));
        self::assertNull($scope->lineSeparator('tt_content', 'bodytext'));
        self::assertSame(['tx_news_domain_model_news'], $scope->recordTables);
    }
}
