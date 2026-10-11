<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\ImmediateResponseException;

/**
 * Keeps an editor's Basic Auth login when frontend editing loses its TYPO3 session.
 *
 * The visual editor iframe and its frontend endpoints bypass the backend AJAX
 * guard. Their session denials must not reject the independent Apache login.
 * Authentication challenges from frontend APIs retain their original status.
 */
final readonly class BasicAuthSafeFrontendEditDenial implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!self::cameThroughBasicAuth($request) || !self::isEditingRequest($request)) {
            return $handler->handle($request);
        }

        try {
            $response = $handler->handle($request);
        } catch (ImmediateResponseException $exception) {
            // The visual editor intentionally bypasses outer middleware when
            // showing its login message. Preserve that behavior after mapping.
            $response = self::mapDenial($exception->getResponse());
            if ($response === $exception->getResponse()) {
                throw $exception;
            }
            $exceptionClass = $exception::class;
            throw new $exceptionClass($response, $exception->getCode());
        }

        return self::mapDenial($response);
    }

    private static function mapDenial(ResponseInterface $response): ResponseInterface
    {
        return $response->getStatusCode() === 401 && !$response->hasHeader('WWW-Authenticate')
            ? $response->withStatus(403)
            : $response;
    }

    private static function cameThroughBasicAuth(ServerRequestInterface $request): bool
    {
        $server = $request->getServerParams();
        $authType = $server['AUTH_TYPE'] ?? '';
        $user = $server['PHP_AUTH_USER'] ?? '';

        return (is_string($authType) && strcasecmp($authType, 'Basic') === 0)
            || (is_string($user) && $user !== '');
    }

    private static function isEditingRequest(ServerRequestInterface $request): bool
    {
        $query = $request->getQueryParams();

        return isset($query['editMode'])
            || isset($query['elementLibrary'])
            || isset($query['elementLibrarySearch'])
            || isset($query['veFieldOptions']);
    }
}
