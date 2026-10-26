<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\ViewHelpers\Backend;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use Webconsulting\SitePackage\Backend\LoginEnvironment;

/**
 * The environment of this installation for the backend login layout.
 *
 * A ViewHelper rather than a view variable: the layout is rendered by the
 * LoginController, which dispatches the login event, and by the
 * ResetPasswordController, which does not. This way the badge and the footnote
 * look the same on the password-reset pages.
 *
 * ```
 *   <f:variable name="environment" value="{sp:backend.loginEnvironment()}" />
 *   {environment.key}, {environment.label}, {environment.project}, {environment.websiteBy}, {environment.websiteByUrl}
 * ```
 */
final class LoginEnvironmentViewHelper extends AbstractViewHelper
{
    /**
     * @return array{key: string, label: string, project: string, websiteBy: string, websiteByUrl: string}
     */
    public function render(): array
    {
        return LoginEnvironment::current()->toArray();
    }
}
