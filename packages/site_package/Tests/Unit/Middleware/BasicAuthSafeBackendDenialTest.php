<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use Webconsulting\SitePackage\Middleware\BasicAuthSafeBackendDenial;

/**
 * A backend AJAX 401 behind basic auth goes out as 403, so Chrome keeps the
 * basic-auth login; everything else passes unchanged.
 */
final class BasicAuthSafeBackendDenialTest extends TestCase
{
    /**
     * @return iterable<string, array{0: array<string, string>, 1: bool, 2: int, 3: int}>
     */
    public static function cases(): iterable
    {
        yield 'ajax 401 behind basic auth becomes 403' => [['AUTH_TYPE' => 'Basic', 'PHP_AUTH_USER' => 'lab'], true, 401, 403];
        yield 'the auth type alone is enough' => [['AUTH_TYPE' => 'basic'], true, 401, 403];
        yield 'the user alone is enough' => [['PHP_AUTH_USER' => 'lab'], true, 401, 403];
        yield 'ajax 401 without basic auth stays' => [[], true, 401, 401];
        yield 'a module 401 stays: the sudo mode redirects with it' => [['AUTH_TYPE' => 'Basic'], false, 401, 401];
        yield 'other statuses pass' => [['AUTH_TYPE' => 'Basic'], true, 200, 200];
        yield 'a 403 stays a 403' => [['AUTH_TYPE' => 'Basic'], true, 403, 403];
    }

    /**
     * @param array<string, string> $serverParams
     */
    #[Test]
    #[DataProvider('cases')]
    public function mapsOnlyBackendAjaxDenialsBehindBasicAuth(array $serverParams, bool $ajax, int $status, int $expected): void
    {
        $request = (new ServerRequest('https://typo3-lab.example/typo3/ajax/icons', 'GET', 'php://input', [], $serverParams))
            ->withAttribute('route', new Route('/ajax/icons', $ajax ? ['ajax' => true] : []));
        $handler = new class ($status) implements RequestHandlerInterface {
            public function __construct(private readonly int $status) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(statusCode: $this->status);
            }
        };

        $response = (new BasicAuthSafeBackendDenial())->process($request, $handler);

        self::assertSame($expected, $response->getStatusCode());
    }

    #[Test]
    public function keepsTheResponseWithoutARoute(): void
    {
        $request = new ServerRequest('https://typo3-lab.example/typo3/', 'GET', 'php://input', [], ['AUTH_TYPE' => 'Basic']);
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(statusCode: 401);
            }
        };

        self::assertSame(401, (new BasicAuthSafeBackendDenial())->process($request, $handler)->getStatusCode());
    }
}
