<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\ConsumableNonce;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

/**
 * The TYPO3 trademark notice on every HTML page of the lab, in the page's
 * language: "TYPO3 is a registered trademark of the TYPO3 Association …".
 *
 * Themes that print it in their own footer (Desiderio's
 * `desiderio.footer.trademarkNotice`) mark it with `data-trademark-notice`;
 * such pages are left alone, so the notice never appears twice. Every other
 * theme gets one small line before `</body>`. Runs inside
 * content-length-headers, so the length header counts the line; the page
 * cache keeps the page without it.
 */
final readonly class TrademarkNotice implements MiddlewareInterface
{
    public const MARKER = 'data-trademark-notice';
    private const LABEL = 'LLL:EXT:site_package/Resources/Private/Language/trademark.xlf:notice';

    public function __construct(private LanguageServiceFactory $languageServiceFactory) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $language = $request->getAttribute('language');
        if (!$language instanceof SiteLanguage
            || !str_starts_with(strtolower($response->getHeaderLine('Content-Type')), 'text/html')
            || (int)($request->getQueryParams()['type'] ?? 0) !== 0
            || isset($request->getQueryParams()['elPreview'])
        ) {
            return $response;
        }
        $text = $this->languageServiceFactory->createFromSiteLanguage($language)->sL(self::LABEL);
        $nonce = $request->getAttribute('nonce');
        // With a content security policy, the style needs the request's nonce.
        $html = self::withNotice((string)$response->getBody(), $text, $language->getLocale()->getLanguageCode(), $nonce instanceof ConsumableNonce ? $nonce->consumeInline() : null);
        if ($html === null) {
            return $response;
        }
        $body = new Stream('php://temp', 'rw');
        $body->write($html);
        return $response->withBody($body)->withoutHeader('Content-Length');
    }

    /**
     * The page with the notice before its last `</body>`, or null when the
     * page has no body end, already shows the notice, or the text is empty.
     */
    public static function withNotice(string $html, string $text, string $lang, ?string $nonce = null): ?string
    {
        $end = strripos($html, '</body>');
        if ($text === '' || $end === false || str_contains($html, self::MARKER)) {
            return null;
        }
        $style = '<style' . ($nonce !== null && $nonce !== '' ? ' nonce="' . htmlspecialchars($nonce) . '"' : '') . '>'
            . '.site-trademark-notice{margin:0;padding:14px 20px 22px;font-size:12px;line-height:1.5;text-align:center;}'
            . '</style>';
        $notice = '<p class="site-trademark-notice" ' . self::MARKER . ' lang="' . htmlspecialchars($lang) . '">'
            . htmlspecialchars($text) . '</p>';
        return substr($html, 0, $end) . $style . $notice . substr($html, $end);
    }
}
