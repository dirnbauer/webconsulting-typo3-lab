<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webconsulting\SitePackage\Middleware\TrademarkNotice;

/**
 * The TYPO3 trademark notice goes once before the page's body end, in the
 * page language, escaped, with the CSP nonce on its style.
 */
final class TrademarkNoticeTest extends TestCase
{
    private const TEXT = 'TYPO3 is a registered trademark of the TYPO3 Association.';

    #[Test]
    public function insertsTheNoticeBeforeTheLastBodyEnd(): void
    {
        $html = TrademarkNotice::withNotice('<html><body><p>Hi</p><!-- </body> --></body></html>', self::TEXT, 'de');

        self::assertNotNull($html);
        self::assertStringEndsWith('<p class="site-trademark-notice" data-trademark-notice lang="de">' . self::TEXT . '</p></body></html>', $html);
        self::assertSame(1, substr_count($html, 'data-trademark-notice'));
    }

    #[Test]
    public function leavesPagesAloneThatShowTheNoticeAlready(): void
    {
        $page = '<html><body><footer><p data-trademark-notice>…</p></footer></body></html>';

        self::assertNull(TrademarkNotice::withNotice($page, self::TEXT, 'en'));
    }

    #[Test]
    public function leavesFragmentsAndEmptyTextsAlone(): void
    {
        self::assertNull(TrademarkNotice::withNotice('<div>fragment</div>', self::TEXT, 'en'));
        self::assertNull(TrademarkNotice::withNotice('<html><body></body></html>', '', 'en'));
    }

    #[Test]
    public function escapesTheTextAndCarriesTheNonce(): void
    {
        $html = (string)TrademarkNotice::withNotice('<html><body></body></html>', 'A <b> & "C"', 'hu', 'n0nce"');

        self::assertStringContainsString('A &lt;b&gt; &amp; &quot;C&quot;', $html);
        self::assertStringContainsString('<style nonce="n0nce&quot;">', $html);
    }
}
