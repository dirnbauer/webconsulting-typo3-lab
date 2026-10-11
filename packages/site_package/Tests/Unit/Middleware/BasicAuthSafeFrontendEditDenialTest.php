<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Http\ServerRequest;
use Webconsulting\SitePackage\Middleware\BasicAuthSafeFrontendEditDenial;

final class BasicAuthSafeFrontendEditDenialTest extends TestCase
{
    /**
     * @return iterable<string, array{0: array<string, string>, 1: array<string, int|string>, 2: int, 3: array<string, string>, 4: int}>
     */
    public static function responses(): iterable
    {
        yield 'visual editor login denial' => [['AUTH_TYPE' => 'Basic'], ['editMode' => 1], 401, [], 403];
        yield 'element library login denial' => [['PHP_AUTH_USER' => 'lab'], ['elementLibrary' => 1], 401, [], 403];
        yield 'element library search login denial' => [['AUTH_TYPE' => 'basic'], ['elementLibrarySearch' => ''], 401, [], 403];
        yield 'field options login denial' => [['AUTH_TYPE' => 'Basic'], ['veFieldOptions' => 1], 401, [], 403];
        yield 'without basic auth' => [[], ['editMode' => 1], 401, [], 401];
        yield 'unrelated frontend API' => [['AUTH_TYPE' => 'Basic'], [], 401, [], 401];
        yield 'API challenge on an editing request' => [['AUTH_TYPE' => 'Basic'], ['editMode' => 1], 401, ['WWW-Authenticate' => 'Bearer realm="api"'], 401];
        yield 'successful editing request' => [['AUTH_TYPE' => 'Basic'], ['editMode' => 1], 200, [], 200];
        yield 'permission denial' => [['AUTH_TYPE' => 'Basic'], ['editMode' => 1], 403, [], 403];
    }

    /**
     * @param array<string, string> $serverParams
     * @param array<string, int|string> $queryParams
     * @param array<string, string> $headers
     */
    #[Test]
    #[DataProvider('responses')]
    public function preservesBasicAuthOnlyForFrontendEditingDenials(array $serverParams, array $queryParams, int $status, array $headers, int $expected): void
    {
        $request = (new ServerRequest('https://typo3-lab.example/target-groups/', 'GET', 'php://input', [], $serverParams))
            ->withQueryParams($queryParams);
        $original = new HtmlResponse('Backend login required', $status, [...$headers, 'Cache-Control' => 'private, no-store']);

        $actual = (new BasicAuthSafeFrontendEditDenial())->process($request, self::handler($original));

        self::assertSame($expected, $actual->getStatusCode());
        self::assertSame($original->getBody(), $actual->getBody());
        self::assertSame($original->getHeaders(), $actual->getHeaders());
        if ($status === $expected) {
            self::assertSame($original, $actual);
        }
    }

    #[Test]
    public function preservesTheVisualEditorImmediateResponseWithoutInvalidatingBasicAuth(): void
    {
        $request = (new ServerRequest('https://typo3-lab.example/target-groups/', 'GET', 'php://input', [], ['AUTH_TYPE' => 'Basic']))
            ->withQueryParams(['editMode' => 1]);
        $original = new HtmlResponse('Backend login required', 401, ['Cache-Control' => 'private, no-store']);

        try {
            (new BasicAuthSafeFrontendEditDenial())->process($request, self::handler($original, true));
            self::fail('The visual editor response must still leave the middleware stack immediately.');
        } catch (ImmediateResponseException $exception) {
            self::assertSame(403, $exception->getResponse()->getStatusCode());
            self::assertSame($original->getBody(), $exception->getResponse()->getBody());
            self::assertSame($original->getHeaders(), $exception->getResponse()->getHeaders());
            self::assertSame(3476089111, $exception->getCode());
        }
    }

    #[Test]
    public function leavesOtherImmediateResponsesUntouched(): void
    {
        $request = (new ServerRequest('https://typo3-lab.example/target-groups/', 'GET', 'php://input', [], ['AUTH_TYPE' => 'Basic']))
            ->withQueryParams(['editMode' => 1]);
        $original = new HtmlResponse('Permission denied', 403);

        try {
            (new BasicAuthSafeFrontendEditDenial())->process($request, self::handler($original, true));
            self::fail('Immediate responses must keep propagating.');
        } catch (ImmediateResponseException $exception) {
            self::assertSame($original, $exception->getResponse());
        }
    }

    private static function handler(ResponseInterface $response, bool $immediate = false): RequestHandlerInterface
    {
        return new class ($response, $immediate) implements RequestHandlerInterface {
            public function __construct(private readonly ResponseInterface $response, private readonly bool $immediate) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                if ($this->immediate) {
                    throw new ImmediateResponseException($this->response, 3476089111);
                }
                return $this->response;
            }
        };
    }
}
