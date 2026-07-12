..  _configuration:

=============
Configuration
=============

Site sets
=========

The extension provides these TYPO3 Site Sets:

..  list-table::
    :header-rows: 1

    *   - Site set
        - Dependencies
        - Purpose
    *   - ``webconsulting/site-package``
        -
        - Base defaults, Admin Panel, RTE, MCP table metadata, and Cowriter
          preload middleware.
    *   - ``webconsulting/site-package-search``
        - ``webconsulting/site-package``, ``webconsulting/solr-defaults``
        - Solr defaults and numbered pagination partials.
    *   - ``webconsulting/site-package-blog``
        - ``webconsulting/site-package-search``,
          ``webconsulting/desiderio-blog-standalone``
        - Blog standalone rendering with Desiderio templates.
    *   - ``webconsulting/site-package-blog-bootstrap``
        - ``webconsulting/site-package-search``, ``blog/bootstrap-53``
        - Blog Bootstrap 5.3 demo rendering.
    *   - ``webconsulting/site-package-camino``
        - ``webconsulting/site-package-search``, ``typo3/theme-camino``
        - Camino demo rendering.
    *   - ``webconsulting/site-package-workos``
        - ``webconsulting/site-package``, ``webconsulting/desiderio``
        - Lab-only WorkOS Fluid overrides, direct CType rendering bridge, and
          semantic shadcn token CSS.

Configured lab sites
====================

The current lab site configurations use the package as follows:

..  list-table::
    :header-rows: 1

    *   - Site configuration
        - Site Set dependencies from this package
        - Additional dependencies
    *   - :file:`config/sites/camino`
        - ``webconsulting/site-package-camino``
        -
    *   - :file:`config/sites/blog`
        - ``webconsulting/site-package-blog``
        - ``georgringer/news``, ``webconsulting/desiderio-news``
    *   - :file:`config/sites/typo3-blog`
        - ``webconsulting/site-package-blog``
        -
    *   - :file:`config/sites/14lts`
        - ``webconsulting/site-package-blog-bootstrap``
        -
    *   - :file:`config/sites/mtug-camp-munich-2026`
        - ``webconsulting/site-package-search``
        - ``studiomitte/friendlycaptcha``,
          ``webconsulting/desiderio-powermail``,
          ``webconsulting/desiderio-content-elements``,
          ``webconsulting/desiderio-preset-corporate``,
          ``desiderio/content-blocks-bundle``
    *   - :file:`config/sites/desiderio-corporate-starter`
        - ``webconsulting/site-package-search``
        - ``webconsulting/desiderio-preset-corporate``
    *   - :file:`config/sites/desiderio`
        - ``webconsulting/site-package-workos``
        - ``webconsulting/desiderio-preset-corporate``,
          ``webconsulting/desiderio-content-elements``,
          ``webconsulting/desiderio-powermail``

WorkOS frontend plugin integration
==================================

The hidden ``webconsulting/site-package-workos`` Site Set is intended for the
Desiderio lab site. It supplies template and partial root paths for the Login,
Account, and Team Extbase plugins, bridges the direct WorkOS content types into
Desiderio content-element rendering, and includes the token-based stylesheet.

Create or refresh the matching page tree with:

..  code-block:: bash
    :caption: Seed WorkOS frontend demo pages

    vendor/bin/typo3 sitepackage:seed-workos-frontend

The command writes through TYPO3 DataHandler and can be run repeatedly. WorkOS
controllers, security, and API behavior remain in ``webconsulting/workos-auth``.

Desiderio shadcn/ui templates
=============================

Desiderio demo sites must use the shadcn/ui page template stack from
``webconsulting/desiderio-shadcnui-templates``. In this lab, that Site Set is
always included transitively through one of these dependencies:

* ``webconsulting/desiderio-preset-corporate`` for corporate and campaign sites
* ``webconsulting/desiderio-blog-standalone`` for Blog demo sites through
  ``webconsulting/site-package-blog``

Per-site shadcn style tokens such as ``desiderio.shadcn.preset``,
``desiderio.shadcn.style``, and ``desiderio.shadcn.iconLibrary`` live in the
relevant :file:`config/sites/*/settings.yaml` files and customize the provided
template system. They do not replace the shadcn/ui template Site Set.

Site configuration maintenance for the full lab inventory lives in
:file:`docs/site-configuration.md`.

Cowriter preload middleware
===========================

The PSR-15 middleware in
``Webconsulting\SitePackage\Middleware\CowriterPreloadMiddleware`` preloads the
required ``netresearch/t3-cowriter`` JavaScript modules for Visual Editor edit
mode by using TYPO3's PageRenderer import map API.

Editor defaults
===============

The base site set enables the Cowriter RTE preset:

..  code-block:: typoscript
    :caption: Configuration/Sets/SitePackage/page.tsconfig

    RTE.default.preset = cowriter

Admin Panel defaults
====================

The base site set enables the frontend Admin Panel:

..  code-block:: typoscript
    :caption: Configuration/Sets/SitePackage/setup.typoscript

    config.admPanel = 1

Backend users receive Admin Panel access through user TSconfig:

..  code-block:: typoscript
    :caption: Configuration/user.tsconfig

    admPanel.enable.all = 1

MCP table metadata
==================

The extension registers ``tt_address`` for MCP dynamic tools in
:file:`ext_localconf.php`:

..  code-block:: php
    :caption: ext_localconf.php

    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ms_mcp_server']['tables']['tt_address'] = [
        'label' => 'Addresses',
        'prefix' => 'tt_address',
    ];
