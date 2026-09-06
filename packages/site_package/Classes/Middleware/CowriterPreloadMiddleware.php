<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Page\PageRenderer;

/** Makes Cowriter imports available before Visual Editor renders its iframe. */
final readonly class CowriterPreloadMiddleware implements MiddlewareInterface
{
    public function __construct(
        private PageRenderer $pageRenderer,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (($request->getQueryParams()['editMode'] ?? null) !== null) {
            // Loading one specifier includes the extension's complete import map.
            $this->pageRenderer->loadJavaScriptModule('@netresearch/t3_cowriter/cowriter');
        }

        return $handler->handle($request);
    }
}
