<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Backend\Routing\Route;

/**
 * Keeps the browser's basic-auth login when a TYPO3 backend session ends.
 *
 * Production sits behind Apache basic auth (docker/coolify/apache-vhost.conf).
 * When the backend session ends (idle timeout, logout), TYPO3 answers the
 * backend's AJAX calls with 401: the page tree, the icons, the Easy Workspace
 * badge. Chrome treats any 401 on a request that carried basic-auth
 * credentials as a rejection of those credentials, even without a challenge,
 * and forgets them, so the next click in the open backend tab brought back
 * the browser's password dialog.
 *
 * Such answers go out as 403 instead. Nothing in the backend's JavaScript
 * reads the 401 itself: the login refresh finds the expired session through
 * its own route and asks for the TYPO3 login. Without basic auth in front
 * (DDEV) the middleware changes nothing, and it never touches a request
 * that is not an AJAX route, so the 401 redirect of the sudo mode stays.
 */
final readonly class BasicAuthSafeBackendDenial implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        if ($response->getStatusCode() !== 401 || !self::cameThroughBasicAuth($request) || !self::isAjaxRoute($request)) {
            return $response;
        }

        return $response->withStatus(403);
    }

    private static function cameThroughBasicAuth(ServerRequestInterface $request): bool
    {
        $server = $request->getServerParams();
        $authType = $server['AUTH_TYPE'] ?? '';
        $user = $server['PHP_AUTH_USER'] ?? '';

        return (is_string($authType) && strcasecmp($authType, 'Basic') === 0)
            || (is_string($user) && $user !== '');
    }

    private static function isAjaxRoute(ServerRequestInterface $request): bool
    {
        $route = $request->getAttribute('route');

        return $route instanceof Route && (bool)$route->getOption('ajax');
    }
}
