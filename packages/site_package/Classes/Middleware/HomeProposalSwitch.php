<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\ConsumableNonce;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\SystemResource\Publishing\SystemResourcePublisherInterface;
use TYPO3\CMS\Core\SystemResource\Publishing\UriGenerationOptions;
use TYPO3\CMS\Core\SystemResource\SystemResourceFactory;

/**
 * A switch between three presentation-only proposals for the start page of
 * the Desiderio site (its root page in every language), for the lab only.
 *
 * One small inline script goes into the page head, right after
 * `<meta charset>` (the charset must stay within the first 1024 bytes). It
 * reads the
 * proposal from the URL fragment (`#home=a|b|c`, `#home=off` to leave),
 * remembers it in localStorage (`lab-home`), sets `data-lab-home` on the html
 * element before first paint and loads that proposal's
 * `Resources/Public/Home/<x>/lab-home.css` and `lab-home.js`. Without a
 * choice the page renders exactly as before: no attribute, no request. It
 * never applies inside a frame (Visual Editor, backend page preview) and not
 * to an edit-mode request, so a stored choice cannot leak into editing. The
 * fragment never reaches the server, so the page cache, the static file
 * cache and cHash see one and the same page.
 *
 * Content-Security-Policy: the lab's frontend policy uses neither nonces nor
 * collected hashes, so EventListener\HomeProposalSwitchPolicy lists this
 * script's SHA-256 in script-src-elem for the same pages. The script also
 * carries the request's nonce when one is in use (this middleware runs inside
 * csp-headers).
 */
final readonly class HomeProposalSwitch implements MiddlewareInterface
{
    public const SITE_IDENTIFIER = 'desiderio';
    public const PROPOSALS = ['a', 'b', 'c'];
    public const STORAGE_KEY = 'lab-home';
    public const MARKER = 'data-lab-home-switch';
    private const RESOURCE = 'EXT:site_package/Resources/Public/Home/%s/lab-home.%s';

    public function __construct(
        private SystemResourceFactory $systemResourceFactory,
        private SystemResourcePublisherInterface $resourcePublisher,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        if (!self::isStartPage($request)
            || !str_starts_with(strtolower($response->getHeaderLine('Content-Type')), 'text/html')
        ) {
            return $response;
        }
        $proposals = $this->proposalUris($request);
        $nonce = $request->getAttribute('nonce');
        $html = self::withSwitch(
            (string)$response->getBody(),
            $proposals,
            $nonce instanceof ConsumableNonce ? $nonce->consumeInline() : null
        );
        if ($html === null) {
            return $response;
        }
        $body = new Stream('php://temp', 'rw');
        $body->write($html);
        return $response->withBody($body)->withoutHeader('Content-Length');
    }

    /**
     * The root page of the Desiderio site in any language, as a plain page
     * view: page type 0 as resolved by routing (a routed suffix such as
     * sitemap.xml has its own type), no element-library preview and no
     * Visual Editor edit mode.
     */
    public static function isStartPage(ServerRequestInterface $request): bool
    {
        $site = $request->getAttribute('site');
        $routing = $request->getAttribute('routing');
        $query = $request->getQueryParams();
        return $site instanceof Site
            && $site->getIdentifier() === self::SITE_IDENTIFIER
            && $routing instanceof PageArguments
            && $routing->getPageId() === $site->getRootPageId()
            && (int)$routing->getPageType() === 0
            && (int)($query['type'] ?? 0) === 0
            && !isset($query['elPreview'])
            && !isset($query['editMode']);
    }

    /**
     * The page with the switch script right after its `<meta charset>` (or
     * right after the `<head>` opening tag when the head declares no charset),
     * or null when the page has no head or carries the switch already.
     *
     * @param array<string, array{0: string, 1: string}> $proposals proposal => [css URI, js URI]
     */
    public static function withSwitch(string $html, array $proposals, ?string $nonce = null): ?string
    {
        if ($proposals === [] || str_contains($html, self::MARKER)
            || preg_match('/<head(?:\s[^>]*)?>/i', $html, $match, PREG_OFFSET_CAPTURE) !== 1
        ) {
            return null;
        }
        $end = $match[0][1] + strlen($match[0][0]);
        $headEnd = stripos($html, '</head>', $end);
        if (preg_match('/<meta\s[^>]*\bcharset\s*=[^>]*>/i', $html, $charset, PREG_OFFSET_CAPTURE, $end) === 1
            && ($headEnd === false || $charset[0][1] < $headEnd)
        ) {
            $end = $charset[0][1] + strlen($charset[0][0]);
        }
        return substr($html, 0, $end) . self::script($proposals, $nonce) . substr($html, $end);
    }

    /**
     * @param array<string, array{0: string, 1: string}> $proposals
     */
    public static function script(array $proposals, ?string $nonce = null): string
    {
        return '<script ' . self::MARKER
            . ($nonce !== null && $nonce !== '' ? ' nonce="' . htmlspecialchars($nonce) . '"' : '')
            . '>' . self::code($proposals) . '</script>';
    }

    /**
     * The script's text, exactly as hashed for the policy.
     *
     * @param array<string, array{0: string, 1: string}> $proposals
     */
    public static function code(array $proposals): string
    {
        $map = json_encode($proposals, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $names = implode('|', array_map(static fn (string $name): string => preg_quote($name, '/'), array_keys($proposals)));
        $key = json_encode(self::STORAGE_KEY, JSON_THROW_ON_ERROR);
        // Reads #home=<x> (also among other fragment parts, "&"-separated),
        // "off" forgets the choice. A changed #home= on the same page reloads,
        // so a link can switch proposals.
        // Never inside a frame: the Visual Editor and backend previews share
        // the origin, and with it the stored choice.
        return '(function(d,w){if(w.top!==w)return;var U=' . $map . ',K=' . $key . ',R=/(?:^#|&)home=(' . $names . '|off)(?=&|$)/;'
            . 'function f(){var m=w.location.hash.match(R);return m?m[1]:null}'
            . 'var h=f(),v=null;try{v=w.localStorage.getItem(K)}catch(e){}'
            . 'if(h){v=h==="off"?null:h;try{v?w.localStorage.setItem(K,v):w.localStorage.removeItem(K)}catch(e){}}'
            . 'w.addEventListener("hashchange",function(){var n=f();if(n&&(n==="off"?null:n)!==(v||null))w.location.reload()});'
            . 'if(!v||!Object.prototype.hasOwnProperty.call(U,v))return;'
            . 'd.documentElement.setAttribute("data-lab-home",v);'
            . 'var l=d.createElement("link");l.rel="stylesheet";l.href=U[v][0];l.setAttribute("blocking","render");d.head.appendChild(l);'
            . 'var s=d.createElement("script");s.src=U[v][1];s.async=false;d.head.appendChild(s)'
            . '})(document,window);';
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function proposalUris(ServerRequestInterface $request): array
    {
        $uris = [];
        foreach (self::PROPOSALS as $proposal) {
            $uris[$proposal] = [
                $this->uri(sprintf(self::RESOURCE, $proposal, 'css'), $request),
                $this->uri(sprintf(self::RESOURCE, $proposal, 'js'), $request),
            ];
        }
        return $uris;
    }

    private function uri(string $resource, ServerRequestInterface $request): string
    {
        return (string)$this->resourcePublisher->generateUri(
            $this->systemResourceFactory->createPublicResource($resource),
            $request,
            new UriGenerationOptions(cacheBusting: true),
        );
    }
}
