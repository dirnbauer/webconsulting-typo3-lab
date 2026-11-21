<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\Site;
use Webconsulting\SitePackage\Middleware\HomeProposalSwitch;

/**
 * The start page proposal switch: only on the Desiderio root page, one inline
 * script first in the head, with the nonce, and the proposals' files only
 * named in it (nothing is loaded without a choice).
 */
final class HomeProposalSwitchTest extends TestCase
{
    private const PROPOSALS = [
        'a' => ['/_assets/x/Home/a/lab-home.css', '/_assets/x/Home/a/lab-home.js'],
        'b' => ['/_assets/x/Home/b/lab-home.css', '/_assets/x/Home/b/lab-home.js'],
        'c' => ['/_assets/x/Home/c/lab-home.css', '/_assets/x/Home/c/lab-home.js'],
    ];

    #[Test]
    public function putsOneScriptRightAfterTheCharset(): void
    {
        $html = (string)HomeProposalSwitch::withSwitch('<html lang="en"><head data-x="1"><meta charset="utf-8"><link rel="stylesheet" href="/a.css"></head><body></body></html>', self::PROPOSALS);

        self::assertStringStartsWith('<html lang="en"><head data-x="1"><meta charset="utf-8"><script data-lab-home-switch>', $html);
        self::assertSame(1, substr_count($html, '<script'));
        self::assertStringNotContainsString('data-lab-home=', $html, 'no attribute is rendered on the server');
        self::assertLessThan(strpos($html, '<link'), strpos($html, '</script>'), 'before every stylesheet');
    }

    #[Test]
    public function keepsTheCharsetWithinTheFirstKilobyte(): void
    {
        $html = (string)HomeProposalSwitch::withSwitch('<!DOCTYPE html><html><head><meta charset="utf-8"><title>x</title></head></html>', self::PROPOSALS);

        self::assertSame(strlen('<!DOCTYPE html><html><head>'), strpos($html, '<meta charset'));
    }

    #[Test]
    public function fallsBackToTheHeadTagWithoutACharsetInTheHead(): void
    {
        $html = (string)HomeProposalSwitch::withSwitch('<html><head><title>x</title></head><body><meta charset="utf-8"></body></html>', self::PROPOSALS);

        self::assertStringStartsWith('<html><head><script data-lab-home-switch>', $html);
    }

    #[Test]
    public function staysOutOfFrames(): void
    {
        // The Visual Editor and backend previews load the page in a frame on
        // the same origin, where the stored choice would otherwise apply.
        self::assertStringStartsWith('(function(d,w){if(w.top!==w)return;', HomeProposalSwitch::code(self::PROPOSALS));
    }

    #[Test]
    public function namesEveryProposalAndTheStorageKey(): void
    {
        $script = HomeProposalSwitch::script(self::PROPOSALS);

        foreach (self::PROPOSALS as $files) {
            self::assertStringContainsString('"' . $files[0] . '"', $script);
            self::assertStringContainsString('"' . $files[1] . '"', $script);
        }
        self::assertStringContainsString('"lab-home"', $script);
        self::assertStringContainsString('home=(a|b|c|off)', $script);
        self::assertStringContainsString('"data-lab-home"', $script);
    }

    #[Test]
    public function hashesExactlyTheScriptText(): void
    {
        // The policy lists the SHA-256 of code(); it must be the element's whole text.
        self::assertSame(
            '<script data-lab-home-switch>' . HomeProposalSwitch::code(self::PROPOSALS) . '</script>',
            HomeProposalSwitch::script(self::PROPOSALS)
        );
    }

    #[Test]
    public function carriesTheNonceEscaped(): void
    {
        self::assertStringStartsWith('<script data-lab-home-switch nonce="n0nce&quot;">', HomeProposalSwitch::script(self::PROPOSALS, 'n0nce"'));
    }

    #[Test]
    public function keepsMarkupOutOfTheScript(): void
    {
        $script = HomeProposalSwitch::script(['a' => ['/x</script><b>.css', '/x.js']]);

        self::assertSame(1, substr_count($script, '</script>'));
        self::assertStringNotContainsString('<b>', $script);
    }

    #[Test]
    public function leavesPagesWithoutHeadOrWithTheSwitchAlone(): void
    {
        self::assertNull(HomeProposalSwitch::withSwitch('<div>fragment</div>', self::PROPOSALS));
        self::assertNull(HomeProposalSwitch::withSwitch('<html><header></header></html>', self::PROPOSALS));
        $once = (string)HomeProposalSwitch::withSwitch('<html><head></head></html>', self::PROPOSALS);
        self::assertNull(HomeProposalSwitch::withSwitch($once, self::PROPOSALS));
        self::assertNull(HomeProposalSwitch::withSwitch('<html><head></head></html>', []));
    }

    #[Test]
    public function appliesOnlyToTheDesiderioRootPage(): void
    {
        $desiderio = new Site('desiderio', 505, ['base' => 'https://example.org/']);
        $other = new Site('astryx-typo3', 1290, ['base' => 'https://example.org/astryx/']);

        self::assertTrue(HomeProposalSwitch::isStartPage($this->request($desiderio, 505)));
        self::assertFalse(HomeProposalSwitch::isStartPage($this->request($desiderio, 506)), 'another page of the site');
        self::assertFalse(HomeProposalSwitch::isStartPage($this->request($other, 1290)), 'the start page of another site');
        self::assertFalse(HomeProposalSwitch::isStartPage($this->request($desiderio, 505, ['type' => '1777200001'])), 'a page type');
        self::assertFalse(HomeProposalSwitch::isStartPage($this->request($desiderio, 505, ['elPreview' => 'x'])), 'an element preview');
        self::assertFalse(HomeProposalSwitch::isStartPage($this->request($desiderio, 505, ['editMode' => '1'])), 'the Visual Editor');
        self::assertFalse(HomeProposalSwitch::isStartPage($this->request($desiderio, 505, [], '1533906435')), 'a routed page type (sitemap.xml)');
        self::assertFalse(HomeProposalSwitch::isStartPage(new ServerRequest('https://example.org/')), 'no site');
    }

    /**
     * @param array<string, string> $query
     */
    private function request(Site $site, int $pageId, array $query = [], string $pageType = '0'): ServerRequest
    {
        return (new ServerRequest('https://example.org/'))
            ->withAttribute('site', $site)
            ->withAttribute('routing', new PageArguments($pageId, $pageType, []))
            ->withQueryParams($query);
    }
}
