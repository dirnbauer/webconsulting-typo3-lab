<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use Webconsulting\SitePackage\Middleware\SplitCombinedSolrTypeFilter;

/**
 * A combined content-type filter left by the old route enhancer redirects to
 * one filter per type; every other request passes untouched.
 */
final class SplitCombinedSolrTypeFilterTest extends TestCase
{
    /**
     * @return iterable<string, array{0: array<array-key, mixed>, 1: list<string>|null}>
     */
    public static function filters(): iterable
    {
        yield 'two types joined by a comma' => [
            ['type:pages,tx_news_domain_model_news'],
            ['type:pages', 'type:tx_news_domain_model_news'],
        ];
        yield 'joined twice, escaped with °' => [
            ['type:pages,tx_news_domain_model_news°tx_nrllm_skill°tx_skillflow_skill'],
            ['type:pages', 'type:tx_news_domain_model_news', 'type:tx_nrllm_skill', 'type:tx_skillflow_skill'],
        ];
        yield 'other filters keep their place' => [
            [3 => 'category:News', 5 => 'type:pages,tx_nrllm_skill'],
            ['category:News', 'type:pages', 'type:tx_nrllm_skill'],
        ];
        yield 'duplicates and empty parts go' => [
            ['type:pages', 'type:pages,,tx_news_domain_model_news,'],
            ['type:pages', 'type:tx_news_domain_model_news'],
        ];
        yield 'single types need nothing' => [['type:pages', 'type:tx_nrllm_skill'], null];
        yield 'a category with a comma is not a type' => [['category:Tips, tricks'], null];
        yield 'nested arrays are left to EXT:solr' => [[['type:pages,x']], null];
    }

    /**
     * @param array<array-key, mixed> $filters
     * @param list<string>|null $expected
     */
    #[Test]
    #[DataProvider('filters')]
    public function splitsOnlyCombinedTypeFilters(array $filters, ?array $expected): void
    {
        self::assertSame($expected, SplitCombinedSolrTypeFilter::split($filters));
    }

    #[Test]
    public function redirectsPermanentlyToOneFilterPerType(): void
    {
        $query = ['tx_solr' => ['filter' => ['type:pages,tx_news_domain_model_news°tx_nrllm_skill'], 'q' => 'page 1994']];
        $request = (new ServerRequest('https://typo3-lab.example/search/?' . http_build_query($query), 'GET'))
            ->withQueryParams($query);

        $response = (new SplitCombinedSolrTypeFilter())->process($request, self::handler());

        self::assertSame(301, $response->getStatusCode());
        parse_str((string)parse_url($response->getHeaderLine('Location'), PHP_URL_QUERY), $target);
        self::assertSame('/search/', parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
        self::assertSame(
            ['filter' => ['type:pages', 'type:tx_news_domain_model_news', 'type:tx_nrllm_skill'], 'q' => 'page 1994'],
            $target['tx_solr'] ?? null
        );
    }

    #[Test]
    public function passesEverythingElse(): void
    {
        $query = ['tx_solr' => ['filter' => ['type:pages'], 'q' => 'typo3']];
        $plain = (new ServerRequest('https://typo3-lab.example/search/', 'GET'))->withQueryParams($query);
        $post = (new ServerRequest('https://typo3-lab.example/search/', 'POST'))
            ->withQueryParams(['tx_solr' => ['filter' => ['type:pages,tx_news_domain_model_news']]]);

        self::assertSame(204, (new SplitCombinedSolrTypeFilter())->process($plain, self::handler())->getStatusCode());
        self::assertSame(204, (new SplitCombinedSolrTypeFilter())->process($post, self::handler())->getStatusCode());
    }

    private static function handler(): RequestHandlerInterface
    {
        return new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response('php://temp', 204);
            }
        };
    }
}
