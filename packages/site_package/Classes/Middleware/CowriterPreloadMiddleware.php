<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/**
 * Ensures the netresearch/t3-cowriter CKEditor plugin specifiers are
 * present in the Visual Editor iframe's <script type="importmap">.
 *
 * The problem
 * ───────────
 * TYPO3 v14's ImportMap only emits entries from extensions in its
 * internal $extensionsToLoad list. An extension lands in that list
 * via PageRenderer::loadJavaScriptModule() (or as a recursive
 * dependency of one that does).
 *
 * Visual-editor's EditModeService::init() explicitly loads
 * @typo3/visual-editor/Frontend/index — that pulls in backend +
 * rte_ckeditor as dependencies. But t3-cowriter is never explicitly
 * loaded by anyone, so its modules don't reach the iframe's import
 * map. Inside the iframe, CKEditor's init-ckeditor-instance.js does
 *
 *     await import('@netresearch/t3_cowriter/cowriter')
 *
 * which then fails with
 *
 *     Failed to resolve module specifier '@netresearch/t3_cowriter/cowriter'
 *
 * for every <ve-editable-rich-text> field.
 *
 * The fix
 * ───────
 * Run as a frontend middleware AFTER prepare-tsfe-rendering (so we
 * have a request context) and BEFORE the response is produced.
 * When ?editMode=1 is set and t3-cowriter is installed, call
 * loadJavaScriptModule() for the three cowriter specifiers — that
 * pushes t3-cowriter into $extensionsToLoad and its imports flow
 * into the emitted iframe importmap.
 */
final readonly class CowriterPreloadMiddleware implements MiddlewareInterface
{
    private const COWRITER_SPECIFIERS = [
        '@netresearch/t3_cowriter/cowriter',
        '@netresearch/t3_cowriter/AIService',
        '@netresearch/t3_cowriter/CowriterDialog',
    ];

    public function __construct(
        private PageRenderer $pageRenderer,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!ExtensionManagementUtility::isLoaded('t3_cowriter')) {
            return $handler->handle($request);
        }
        if (($request->getQueryParams()['editMode'] ?? null) === null) {
            return $handler->handle($request);
        }
        foreach (self::COWRITER_SPECIFIERS as $specifier) {
            $this->pageRenderer->loadJavaScriptModule($specifier);
        }
        return $handler->handle($request);
    }
}
