<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\TypoScript\IncludeTree\Event\ModifyLoadedPageTsConfigEvent;

/**
 * A site built with one theme offers no content types of the other one.
 *
 * Desiderio and Astryx put their elements into the same wizard groups (hero, features, pricing …),
 * so the "New content element" wizard of a Desiderio page listed 250 Astryx types next to its own,
 * and the other way round. An element of the other theme renders without its styles; the Word
 * import, which picks from what the wizard offers, proposed them too. Each site's sets say which
 * theme it uses; the other theme's CTypes go into TCEFORM.tt_content.CType.removeItems. The lists
 * come from TCA, so a theme's new elements are covered without a change here.
 */
#[AsEventListener('site-package/hide-other-themes-content-types')]
final readonly class HideOtherThemesContentTypes
{
    /** Site set prefix of each theme => prefix of its content element types. */
    private const array THEMES = [
        'webconsulting/desiderio' => 'desiderio_',
        'webconsulting/astryx-typo3' => 'astryx_typo3_',
    ];

    public function __construct(private SiteFinder $siteFinder) {}

    public function __invoke(ModifyLoadedPageTsConfigEvent $event): void
    {
        $pageUid = self::deepestPageUid($event->getRootLine());
        if ($pageUid <= 0) {
            return;
        }
        try {
            $sets = $this->siteFinder->getSiteByPageId($pageUid)->getSets();
        } catch (SiteNotFoundException) {
            return;
        }

        $removed = self::removedTypes($sets, self::contentTypes());
        if ($removed !== []) {
            $event->addTsConfig('TCEFORM.tt_content.CType.removeItems := addToList(' . implode(',', $removed) . ')');
        }
    }

    /**
     * The other theme's content types, when the sets name exactly one theme.
     *
     * @param list<string> $sets
     * @param list<string> $contentTypes
     * @return list<string>
     */
    public static function removedTypes(array $sets, array $contentTypes): array
    {
        $themes = [];
        foreach (self::THEMES as $setPrefix => $typePrefix) {
            foreach ($sets as $set) {
                if (str_starts_with($set, $setPrefix)) {
                    $themes[$typePrefix] = true;
                }
            }
        }
        if (count($themes) !== 1) {
            return [];
        }
        $own = array_key_first($themes);
        $others = array_values(array_filter(self::THEMES, static fn(string $prefix): bool => $prefix !== $own));

        return array_values(array_filter(
            $contentTypes,
            static fn(string $type): bool => array_any($others, static fn(string $prefix): bool => str_starts_with($type, $prefix)),
        ));
    }

    /**
     * @param array<mixed> $rootLine
     */
    private static function deepestPageUid(array $rootLine): int
    {
        $uid = 0;
        foreach ($rootLine as $page) {
            if (is_array($page) && is_numeric($page['uid'] ?? null) && (int)$page['uid'] > 0) {
                $uid = (int)$page['uid'];
            }
        }

        return $uid;
    }

    /**
     * @return list<string>
     */
    private static function contentTypes(): array
    {
        $items = $GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'] ?? [];
        $types = [];
        foreach (is_array($items) ? $items : [] as $item) {
            $value = is_array($item) ? ($item['value'] ?? null) : null;
            if (is_string($value) && $value !== '') {
                $types[] = $value;
            }
        }

        return $types;
    }
}
