<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\ContentAudit;

use PHPUnit\Framework\TestCase;
use Webconsulting\SitePackage\ContentAudit\Canon;
use Webconsulting\SitePackage\ContentAudit\CanonChecker;
use Webconsulting\SitePackage\ContentAudit\FieldRole;
use Webconsulting\SitePackage\ContentAudit\Finding;

final class CanonCheckerTest extends TestCase
{
    private ?CanonChecker $checker = null;

    private function checker(): CanonChecker
    {
        return $this->checker ??= new CanonChecker(Canon::fromFile());
    }

    public function testTheShippedCanonLoads(): void
    {
        $canon = Canon::fromFile();

        self::assertSame(60, $canon->hardLimits['headline']['chars']);
        self::assertSame('ihr', $canon->site('camp')['address'] ?? null);
        self::assertSame('camino', $canon->siteKeyForRoot(99));
        self::assertNotEmpty($canon->spellingUk);
        foreach ($canon->forbidden as $rule) {
            self::assertNotFalse(@preg_match('/' . $rule['pattern'] . '/iu', ''), $rule['pattern']);
        }
    }

    public function testAHeadlineWithinTheLimitsPasses(): void
    {
        self::assertSame([], $this->errors('Themes change without a rebuild', FieldRole::Headline));
    }

    public function testHeadlineLengthAndWordLimitsAreErrors(): void
    {
        $rules = $this->rules($this->errors('This headline goes on for far too many words to stay readable', FieldRole::Headline));

        self::assertContains('words', $rules);
        self::assertContains('length', $rules);
    }

    public function testLongSentencesFailExceptOnLegalPages(): void
    {
        $sentence = str_repeat('word ', 26) . 'end.';

        self::assertContains('sentence', $this->rules($this->errors($sentence, FieldRole::Body)));
        self::assertNotContains('sentence', $this->rules($this->errors($sentence, FieldRole::Body, legal: true)));
    }

    public function testRetiredFactsAreErrors(): void
    {
        self::assertContains('fact', $this->rules($this->errors('Built from 53 typed components.', FieldRole::Card)));
        self::assertContains('fact', $this->rules($this->errors('All 255 content elements ship today.', FieldRole::Card)));
        self::assertNotContains('fact', $this->rules($this->errors('All 244 content elements ship today.', FieldRole::Card)));
    }

    public function testSiteScopedFactsOnlyApplyToTheirSite(): void
    {
        self::assertContains('fact', $this->rules($this->errors('Pick one of seven themes.', FieldRole::Card, site: 'astryx')));
        self::assertNotContains('fact', $this->rules($this->errors('Pick one of seven themes.', FieldRole::Card, site: 'desiderio')));
    }

    public function testDesiderioDemoContentGivesNoRealContactData(): void
    {
        foreach (['Call +43 2626 20156.', 'Write to office@webconsulting.at.', 'Johann Nepomuk Berger-Straße 7/2/14, 7210 Mattersburg'] as $real) {
            self::assertContains('fact', $this->rules($this->errors($real, FieldRole::Card, site: 'desiderio')), $real);
            self::assertContains('fact', $this->rules($this->errors($real, FieldRole::Card, site: 'element-library-de')), $real);
            self::assertNotContains('fact', $this->rules($this->errors($real, FieldRole::Card, site: 'camp')), $real);
        }
        self::assertNotContains('fact', $this->rules($this->errors('Musterstraße 12, 10115 Berlin, +49 30 23125 100', FieldRole::Card, site: 'desiderio')));
        self::assertNotContains('fact', $this->rules($this->errors('Write to privacy@example.com.', FieldRole::Card, site: 'desiderio')));
        self::assertContains('fact', $this->rules($this->errors('Write to privacy@example.com.', FieldRole::Card, site: 'camp')));
        self::assertContains('fact', $this->rules($this->errors('Torstraße 140, 10119 Berlin', FieldRole::Card)));
        self::assertContains('fact', $this->rules($this->errors('Call +41 44 123 45 67.', FieldRole::Card)));
    }

    public function testUsSpellingFailsInEnglishOnly(): void
    {
        self::assertContains('spelling', $this->rules($this->errors('Pick a color.', FieldRole::Card)));
        self::assertNotContains('spelling', $this->rules($this->errors('Pick a colour.', FieldRole::Card)));
        self::assertNotContains('spelling', $this->rules($this->errors('Die color-Palette.', FieldRole::Card, 'de')));
    }

    public function testBannedWordsAndLeftovers(): void
    {
        self::assertContains('banned', $this->rules($this->errors('A world-class theme.', FieldRole::Card)));
        self::assertContains('leftover', $this->rules($this->errors('test2', FieldRole::Headline)));
        self::assertNotContains('leftover', $this->rules($this->errors('Test your forms before launch', FieldRole::Headline)));
    }

    public function testGermanFormOfAddressFollowsTheSite(): void
    {
        self::assertContains('address', $this->rules($this->errors('Hier findest du alle Beiträge.', FieldRole::Card, 'de', 'blog-classico')));
        self::assertNotContains('address', $this->rules($this->errors('Hier finden Sie alle Beiträge.', FieldRole::Card, 'de', 'blog-classico')));
        self::assertNotContains('address', $this->rules($this->errors('Danke, dass ihr dabei wart. Wir sehen euch 2027.', FieldRole::Card, 'de', 'camp')));
        self::assertContains('address', $this->rules($this->errors('Schön, dass du da warst.', FieldRole::Card, 'de', 'camp')));
    }

    public function testCommandLinesAndInlineCodeAreNotSpellChecked(): void
    {
        self::assertNotContains('spelling', $this->rules($this->errors("Send the token like this:\ncurl -H \"Authorization: Bearer x\" https://example.com", FieldRole::Body)));
        self::assertNotContains('spelling', $this->rules($this->errors('Set `color` in the element.', FieldRole::Card)));
        self::assertContains('spelling', $this->rules($this->errors('Pick a color for the element.', FieldRole::Card)));
        self::assertNotContains('spelling', $this->rules($this->errors(\Webconsulting\SitePackage\ContentAudit\CopyMetrics::plainText('<p>Pass <code>organization</code> in the URL.</p>'), FieldRole::Card)));
        self::assertNotContains('spelling', $this->rules($this->errors('The WorkOS Account Center lists every session.', FieldRole::Card)));
        self::assertNotContains('spelling', $this->rules($this->errors("GET /v2/sites/17/rota\nAuthorization: Bearer <token>", FieldRole::Card)));
    }

    public function testSkippedFieldsAreNotChecked(): void
    {
        self::assertSame([], $this->checker()->check('lucide:rocket color', FieldRole::Skip, 'en'));
    }

    /**
     * @return list<Finding>
     */
    private function errors(string $text, FieldRole $role, string $language = 'en', string $site = '', bool $legal = false): array
    {
        return array_values(array_filter(
            $this->checker()->check($text, $role, $language, $site, $legal),
            static fn (Finding $finding): bool => $finding->isError()
        ));
    }

    /**
     * @param list<Finding> $findings
     * @return list<string>
     */
    private function rules(array $findings): array
    {
        return array_map(static fn (Finding $finding): string => $finding->rule, $findings);
    }
}
