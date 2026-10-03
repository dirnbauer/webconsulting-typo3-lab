<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Frontend\Event\ModifyPageLinkConfigurationEvent;

/**
 * A link into another site of this installation goes to that site's version
 * in the reader's language, or to its default language when it has none.
 *
 * TYPO3 builds a link to another site in the current language ID, and a
 * language ID only means something within its own site: German is 1 on the
 * desiderio site, while 1 is English on the camp and blog sites, and Agent
 * Nexus, the corporate starter, the v14 blog and Camino have no 1 at all.
 * From /de/ the homepage's cards to the other sites linked the camp and the
 * blog in English and the other five nowhere; the same happened from /zh/ and
 * /hu/ and to every cross-site link in translated content.
 *
 * The listener matches by language code instead (de, zh, hu, en) and falls
 * back to the target site's default language. A link that asks for a
 * language itself (typolink `language`, `_language` or `L`) is left alone, and
 * so is every link within the current site.
 */
#[AsEventListener('site-package/cross-site-link-language')]
final readonly class CrossSiteLinkLanguage
{
    public function __construct(private SiteFinder $siteFinder) {}

    public function __invoke(ModifyPageLinkConfigurationEvent $event): void
    {
        $configuration = $event->getConfiguration();
        $requested = $configuration['language'] ?? 'current';
        if ($requested !== 'current' && $requested !== '') {
            return;
        }

        $request = $event->getRequest();
        $currentSite = $request->getAttribute('site');
        $currentLanguage = $request->getAttribute('language');
        if (!$currentSite instanceof Site || !$currentLanguage instanceof SiteLanguage) {
            return;
        }

        $queryParameters = $event->getQueryParameters();
        try {
            $targetSite = $this->siteFinder->getSiteByPageId((int)($event->getPage()['uid'] ?? 0), null, (string)($queryParameters['MP'] ?? ''));
        } catch (SiteNotFoundException) {
            return;
        }
        if ($targetSite->getIdentifier() === $currentSite->getIdentifier()) {
            return;
        }

        $configuration['language'] = self::matchingLanguage($targetSite, $currentLanguage)->getLanguageId();
        $event->setConfiguration($configuration);
    }

    /**
     * The target site's enabled language with the reader's language code, or
     * its default language.
     */
    public static function matchingLanguage(Site $targetSite, SiteLanguage $currentLanguage): SiteLanguage
    {
        $code = $currentLanguage->getLocale()->getLanguageCode();
        foreach ($targetSite->getLanguages() as $language) {
            if ($language->getLocale()->getLanguageCode() === $code) {
                return $language;
            }
        }

        return $targetSite->getDefaultLanguage();
    }
}
