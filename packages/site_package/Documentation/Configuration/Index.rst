..  _configuration:

=============
Configuration
=============

Site sets
=========

The extension provides these TYPO3 site sets:

..  list-table::
    :header-rows: 1

    *   - Site set
        - Purpose
    *   - ``webconsulting/site-package``
        - Base defaults, Admin Panel, RTE, MCP table metadata, and Cowriter
          preload middleware.
    *   - ``webconsulting/site-package-search``
        - Solr defaults and numbered pagination partials.
    *   - ``webconsulting/site-package-blog``
        - Blog standalone rendering with Desiderio templates.
    *   - ``webconsulting/site-package-blog-bootstrap``
        - Blog Bootstrap 5.3 demo rendering.
    *   - ``webconsulting/site-package-camino``
        - Camino demo rendering.
    *   - ``webconsulting/site-package-desiderio-corporate``
        - Desiderio corporate demo rendering.

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
