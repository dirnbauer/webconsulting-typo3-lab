<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Context\WorkspaceAspect;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Workspaces\Middleware\WorkspacePreview;
use Webconsulting\SitePackage\Middleware\PreserveFrontendEditorSession;

final class PreserveFrontendEditorSessionTest extends TestCase
{
    /**
     * @return iterable<string, array{0: bool, 1: array<string, int|string>, 2: bool}>
     */
    public static function requests(): iterable
    {
        yield 'editor iframe with a shared preview cookie' => [true, ['editMode' => 1], true];
        yield 'element library with a shared preview cookie' => [true, ['elementLibrary' => 1], true];
        yield 'element search with a shared preview cookie' => [true, ['elementLibrarySearch' => ''], true];
        yield 'field options with a shared preview cookie' => [true, ['veFieldOptions' => 1], true];
        yield 'editing URL carrying a shared preview token' => [true, ['editMode' => 1, 'ADMCMD_prev' => 'shared-preview-token'], true];
        yield 'anonymous editing request' => [false, ['editMode' => 1], false];
        yield 'normal shared preview' => [false, [], false];
        yield 'normal preview while logged in' => [true, [], false];
        yield 'explicit live preview while logged in' => [true, ['ADMCMD_prev' => 'LIVE'], false];
    }

    /**
     * @param array<string, int|string> $query
     */
    #[Test]
    #[DataProvider('requests')]
    public function keepsTheRealEditorForEditingWithoutChangingSharedPreviewCookies(bool $loggedIn, array $query, bool $ignorePreview): void
    {
        $user = self::createStub(BackendUserAuthentication::class);
        $user->user = $loggedIn ? ['uid' => 42, 'username' => 'editor'] : null;
        $context = new Context();
        $context->setAspect('backend.user', new UserAspect($user));
        $request = (new ServerRequest('https://typo3-lab.example/target-groups/'))
            ->withQueryParams($query)
            ->withCookieParams(['ADMCMD_prev' => 'shared-preview-token', 'be_typo_user' => 'backend-session-fixture']);
        $handler = new class implements RequestHandlerInterface {
            public ?ServerRequestInterface $receivedRequest = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->receivedRequest = $request;
                return new HtmlResponse('Editor');
            }
        };

        $response = (new PreserveFrontendEditorSession($context))->process($request, $handler);

        self::assertNotNull($handler->receivedRequest);
        self::assertSame($ignorePreview ? [...$query, 'ADMCMD_prev' => 'IGNORE'] : $query, $handler->receivedRequest->getQueryParams());
        self::assertSame($request->getCookieParams(), $handler->receivedRequest->getCookieParams());
        self::assertSame($request->getUri(), $handler->receivedRequest->getUri());
        self::assertFalse($response->hasHeader('Set-Cookie'));
        if (!$ignorePreview) {
            self::assertSame($request, $handler->receivedRequest);
        }
    }

    #[Test]
    public function coreWorkspacePreviewKeepsTheAuthenticatedEditorsWorkspace(): void
    {
        $user = self::createStub(BackendUserAuthentication::class);
        $user->user = ['uid' => 42, 'username' => 'editor'];
        $context = new Context();
        $context->setAspect('backend.user', new UserAspect($user));
        $context->setAspect('workspace', new WorkspaceAspect(7));
        $request = (new ServerRequest('https://typo3-lab.example/target-groups/'))
            ->withQueryParams(['editMode' => 1])
            ->withCookieParams(['ADMCMD_prev' => 'shared-preview-token']);
        $editor = new class ($context) implements RequestHandlerInterface {
            public function __construct(private readonly Context $context) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                TestCase::assertTrue($this->context->getAspect('backend.user')->isLoggedIn());
                TestCase::assertSame(42, $this->context->getPropertyFromAspect('backend.user', 'id'));
                TestCase::assertSame(7, $this->context->getPropertyFromAspect('workspace', 'id'));
                return new HtmlResponse('<body>Editor</body>');
            }
        };
        $preview = new class ($context, $editor) implements RequestHandlerInterface {
            public function __construct(private readonly Context $context, private readonly RequestHandlerInterface $editor) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                TestCase::assertSame('IGNORE', $request->getQueryParams()['ADMCMD_prev'] ?? null);
                return (new WorkspacePreview($this->context))->process($request, $this->editor);
            }
        };

        $response = (new PreserveFrontendEditorSession($context))->process($request, $preview);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<body>Editor</body>', (string)$response->getBody());
        self::assertFalse($response->hasHeader('Set-Cookie'));
    }
}
