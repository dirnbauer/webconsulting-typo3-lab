<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\EventListener;

use TYPO3\CMS\Backend\LoginProvider\Event\ModifyPageLayoutOnLoginProviderSelectionEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Fluid\View\FluidViewAdapter;

/**
 * The backend login renders with the lab's two-column layout.
 *
 * The LoginController builds its view with the generic ViewFactory and fixed
 * EXT:backend paths, so page TSconfig `templates.typo3/cms-backend` does not
 * reach it; this listener appends the same override folder that page.tsconfig
 * registers for the password-reset views (BackendViewFactory). Only the layout
 * "Login" lives there: the login providers (username/password, WorkOS) keep
 * their own templates, form fields, ids and scripts, and render their sections
 * into this layout. The event is dispatched before the provider's modifyView(),
 * so a provider that adds its own template paths still finds this layout.
 */
#[AsEventListener('site-package/use-lab-backend-login-layout')]
final readonly class UseLabBackendLoginLayout
{
    public const string LAYOUT_ROOT_PATH = 'EXT:site_package/Resources/Private/TemplateOverrides/typo3/cms-backend/Layouts';

    public function __invoke(ModifyPageLayoutOnLoginProviderSelectionEvent $event): void
    {
        $view = $event->getView();
        if (!$view instanceof FluidViewAdapter) {
            return;
        }
        $templatePaths = $view->getRenderingContext()->getTemplatePaths();
        // The last path wins in Fluid's lookup.
        $templatePaths->setLayoutRootPaths([...$templatePaths->getLayoutRootPaths(), self::LAYOUT_ROOT_PATH]);
    }
}
