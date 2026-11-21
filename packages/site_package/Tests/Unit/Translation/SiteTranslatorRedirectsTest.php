<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Translation;

use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Redirects\Hooks\DataHandlerSlugUpdateHook;
use TYPO3\CMS\Redirects\RedirectUpdate\SlugRedirectChangeItemFactory;
use TYPO3\CMS\Redirects\Service\SlugService;
use Webconsulting\SitePackage\Translation\SiteTranslator;

/**
 * A new page translation gets its source's slug. EXT:redirects must not read
 * that as a moved page: on the Netresearch talk (9 new pages, 2026-11-21) it
 * created 22 redirects, rewrote the sub-pages' slugs and then crashed in the
 * CLI while storing a "shadowed redirect" flash message in a session.
 */
final class SiteTranslatorRedirectsTest extends TestCase
{
    public function testTheTranslatorsDataHandlerCountsAsANestedSlugUpdate(): void
    {
        self::assertContains(SlugService::CORRELATION_ID_IDENTIFIER, SiteTranslator::correlationId()->getAspects());
    }

    public function testTheRedirectsHookIgnoresTheTranslatorsWrites(): void
    {
        // Both services are final; the hook's nesting check needs neither.
        $slugService = (new \ReflectionClass(SlugService::class))->newInstanceWithoutConstructor();
        $factory = (new \ReflectionClass(SlugRedirectChangeItemFactory::class))->newInstanceWithoutConstructor();
        $hook = new class ($slugService, $factory) extends DataHandlerSlugUpdateHook {
            public function nested(DataHandler $dataHandler): bool
            {
                return $this->isNestedHookInvocation($dataHandler);
            }
        };
        $dataHandler = $this->createStub(DataHandler::class);
        $dataHandler->method('getCorrelationId')->willReturn(SiteTranslator::correlationId());

        self::assertTrue($hook->nested($dataHandler));
    }
}
