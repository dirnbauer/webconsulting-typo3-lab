<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Page\PageRenderer;
use Webconsulting\SitePackage\Middleware\CowriterPreloadMiddleware;

final class CowriterPreloadMiddlewareTest extends TestCase
{
    #[DataProvider('requests')]
    public function testOnlyEditRequestsLoadCowriterAndAlwaysReachTheHandler(array $query, bool $editing): void
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn($query);
        $response = new Response();
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);
        $renderer = $this->createMock(PageRenderer::class);
        $renderer->expects($editing ? self::once() : self::never())
            ->method('loadJavaScriptModule')->with('@netresearch/t3_cowriter/cowriter');

        self::assertSame($response, (new CowriterPreloadMiddleware($renderer))->process($request, $handler));
    }

    public static function requests(): iterable
    {
        yield 'normal page' => [[], false];
        yield 'null edit mode' => [['editMode' => null], false];
        yield 'edit mode' => [['editMode' => '1'], true];
        yield 'present empty edit mode' => [['editMode' => ''], true];
    }
}
