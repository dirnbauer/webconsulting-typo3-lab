<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\ContentAudit;

use PHPUnit\Framework\TestCase;
use Webconsulting\SitePackage\ContentAudit\Canon;
use Webconsulting\SitePackage\ContentAudit\CanonChecker;
use Webconsulting\SitePackage\ContentAudit\FieldRole;

/**
 * A listed quotation is exempt from exactly the rules it names, for exactly
 * its text (and, in a payload, its record and field). Nothing else is.
 */
final class CanonQuotedCopyTest extends TestCase
{
    private const QUESTION = 'What would we need to clarify before using it in our own TYPO3?';

    private string $directory = '';

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/canon-' . bin2hex(random_bytes(4));
        mkdir($this->directory);
        file_put_contents($this->directory . '/quoted.json', json_encode(['entries' => [
            ['record' => 'NEW_q', 'field' => 'header', 'language' => 'en', 'text' => self::QUESTION, 'rules' => ['limits']],
            ['record' => 'NEW_q_1', 'field' => 'title', 'language' => 'de', 'text' => 'Aus euren bisherigen Projekten', 'rules' => ['address']],
        ]]));
    }

    protected function tearDown(): void
    {
        unlink($this->directory . '/quoted.json');
        rmdir($this->directory);
    }

    private function canon(): Canon
    {
        return Canon::fromArray([
            'limits' => ['hard' => ['headline' => ['chars' => 60, 'words' => 8]]],
            'sites' => ['desiderio' => ['root' => 505, 'language' => 'en', 'address' => 'sie']],
            'address' => ['sie' => '\b(euch|euer|eure|euren|eurem|eurer)\b'],
            'quoted_copy' => ['quoted.json'],
        ], $this->directory);
    }

    public function testThePayloadCheckWaivesOnlyTheListedRecordAndField(): void
    {
        $canon = $this->canon();
        self::assertSame(['limits'], $canon->quotedRules(self::QUESTION, 'en', 'NEW_q', 'header'));
        self::assertSame([], $canon->quotedRules(self::QUESTION, 'en', 'NEW_other', 'header'));
        self::assertSame([], $canon->quotedRules(self::QUESTION, 'en', 'NEW_q', 'subheadline'));
        self::assertSame([], $canon->quotedRules(self::QUESTION . ' Really?', 'en', 'NEW_q', 'header'));
        self::assertSame([], $canon->quotedRules(self::QUESTION, 'de', 'NEW_q', 'header'));
    }

    public function testTheAuditMatchesTextAndLanguage(): void
    {
        $canon = $this->canon();
        self::assertSame(['limits'], $canon->quotedRules('<p>' . self::QUESTION . '</p>', 'en'));
        self::assertSame(['address'], $canon->quotedRules('Aus euren bisherigen Projekten', 'de'));
        self::assertSame([], $canon->quotedRules('Aus euren Projekten', 'de'));
    }

    public function testAWaivedRuleBecomesAWarningAndTheOthersStillApply(): void
    {
        $checker = new CanonChecker($this->canon());
        $plain = $checker->check(self::QUESTION, FieldRole::Headline, 'en', 'desiderio');
        self::assertContains('words', array_map(static fn ($finding): string => $finding->rule, array_filter($plain, static fn ($finding): bool => $finding->isError())));

        $waived = $checker->check(self::QUESTION, FieldRole::Headline, 'en', 'desiderio', false, $checker->quotedRules(self::QUESTION, 'en', 'NEW_q', 'header'));
        self::assertSame([], array_values(array_filter($waived, static fn ($finding): bool => $finding->isError())));
        self::assertSame(['quoted'], array_values(array_map(static fn ($finding): string => $finding->rule, $waived)));

        $address = $checker->check('Aus euren bisherigen Projekten', FieldRole::Headline, 'de', 'desiderio', false, ['address']);
        self::assertSame([], array_values(array_filter($address, static fn ($finding): bool => $finding->isError())));
        $strict = $checker->check('Aus euren bisherigen Projekten', FieldRole::Headline, 'de', 'desiderio');
        self::assertSame(['address'], array_values(array_map(static fn ($finding): string => $finding->rule, array_filter($strict, static fn ($finding): bool => $finding->isError()))));
    }

    public function testAnEntryWithoutRulesIsRejected(): void
    {
        file_put_contents($this->directory . '/quoted.json', json_encode(['entries' => [['text' => 'x', 'rules' => ['everything']]]]));
        $this->expectExceptionCode(1790000003);
        $this->canon();
    }

    public function testTheShippedCanonListsTheTalkQuotations(): void
    {
        $canon = Canon::fromFile();
        self::assertNotEmpty($canon->quotedCopy);
        foreach ($canon->quotedCopy as $entry) {
            self::assertStringStartsWith('NEW_nrtalk_', $entry['record']);
        }
    }
}
