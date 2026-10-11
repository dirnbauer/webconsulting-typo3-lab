<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Context\Context;

/**
 * Keeps shared workspace preview cookies from replacing an authenticated editor.
 *
 * Core's WorkspacePreview creates a read-only preview user from ADMCMD_prev,
 * even when a real backend user is already logged in. Frontend editing needs
 * that real user and their current workspace. IGNORE opts only this request
 * out of shared preview authentication; the preview cookie stays available
 * for normal preview tabs.
 */
final readonly class PreserveFrontendEditorSession implements MiddlewareInterface
{
    public function __construct(private Context $context) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $query = $request->getQueryParams();
        $isEditingRequest = isset($query['editMode'])
            || isset($query['elementLibrary'])
            || isset($query['elementLibrarySearch'])
            || isset($query['veFieldOptions']);

        if ($isEditingRequest && $this->context->getAspect('backend.user')->isLoggedIn()) {
            $request = $request->withQueryParams([...$query, 'ADMCMD_prev' => 'IGNORE']);
        }

        return $handler->handle($request);
    }
}
