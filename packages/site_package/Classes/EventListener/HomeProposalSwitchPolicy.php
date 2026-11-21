<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Directive;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Event\PolicyMutatedEvent;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\HashValue;
use Webconsulting\SitePackage\Middleware\HomeProposalSwitch;

/**
 * Allows the inline script of the start page proposal switch in the
 * Content-Security-Policy: its SHA-256 in script-src-elem, on the pages that
 * get the script and nowhere else. The hash is the same for every visitor, so
 * cached pages keep working.
 */
#[AsEventListener('site-package/home-proposal-switch-policy')]
final readonly class HomeProposalSwitchPolicy
{
    public function __construct(private HomeProposalSwitch $switch) {}

    public function __invoke(PolicyMutatedEvent $event): void
    {
        $request = $event->request;
        if ($request === null || !HomeProposalSwitch::isStartPage($request)) {
            return;
        }
        $hash = HashValue::hash(HomeProposalSwitch::code($this->switch->proposalUris($request)));
        $event->setCurrentPolicy($event->getCurrentPolicy()->extend(Directive::ScriptSrcElem, $hash));
    }
}
