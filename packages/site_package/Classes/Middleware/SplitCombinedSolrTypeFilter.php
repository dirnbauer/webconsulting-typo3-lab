<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;

/**
 * Sends search URLs with a combined content-type filter to the URL with one
 * filter per type.
 *
 * The search pages of the desiderio sites had EXT:solr's
 * SolrFacetMaskAndCombineEnhancer. It joined the selected types into one
 * filter, `type:pages,tx_news_domain_model_news`, but never split them again,
 * so the search looked for a type of that name and found nothing. The next
 * click joined that value once more and escaped its commas as "°" — that is
 * where `type:pages,tx_news_domain_model_news°tx_nrllm_skill` came from. The
 * enhancer is gone from the site configurations; this keeps the links people
 * already have (bookmarks, history, shared URLs) working by answering them
 * with a permanent redirect to the same search, one `type:` filter per type.
 *
 * Only `type:` filters are split: a content type is a table name and never
 * contains a comma, while a category title may.
 */
final readonly class SplitCombinedSolrTypeFilter implements MiddlewareInterface
{
    private const PREFIX = 'type:';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return $handler->handle($request);
        }

        $query = $request->getQueryParams();
        $solr = $query['tx_solr'] ?? null;
        if (!is_array($solr) || !is_array($solr['filter'] ?? null)) {
            return $handler->handle($request);
        }

        $filters = self::split($solr['filter']);
        if ($filters === null) {
            return $handler->handle($request);
        }

        $solr['filter'] = $filters;
        $query['tx_solr'] = $solr;

        return new RedirectResponse(
            $request->getUri()->withQuery(http_build_query($query, '', '&', PHP_QUERY_RFC3986)),
            301
        );
    }

    /**
     * The filters with every combined type value split into one filter per
     * type, or null when no filter needs it (and for anything that is not a
     * plain list of strings, which EXT:solr would not read either).
     *
     * @param array<array-key, mixed> $filters
     * @return list<string>|null
     */
    public static function split(array $filters): ?array
    {
        $result = [];
        $changed = false;
        foreach ($filters as $filter) {
            if (!is_string($filter)) {
                return null;
            }
            $value = str_starts_with($filter, self::PREFIX) ? substr($filter, strlen(self::PREFIX)) : null;
            if ($value === null || (!str_contains($value, ',') && !str_contains($value, '°'))) {
                $result[] = $filter;
                continue;
            }

            $changed = true;
            foreach (preg_split('/[,°]/u', $value) ?: [] as $type) {
                $type = trim($type);
                if ($type !== '') {
                    $result[] = self::PREFIX . $type;
                }
            }
        }

        return $changed ? array_values(array_unique($result)) : null;
    }
}
