..  _introduction:

============
Introduction
============

The extension key is :file:`site_package`. It is a TYPO3 14-only provider
extension for shared site sets, editor defaults, Solr defaults, Admin Panel
defaults, and Visual Editor Cowriter integration.

It consolidates the former local defaults packages into one extension and uses
TYPO3 core APIs such as site sets, PSR-15 middleware, dependency injection, and
the PageRenderer import map API.

Feature inventory
=================

The package provides:

* Base, search, Blog, Blog Bootstrap, Camino, and Desiderio Corporate site
  sets.
* Admin Panel defaults through TypoScript and backend user TSconfig.
* The global ``cowriter`` RTE preset.
* Visual Editor Cowriter module preloading through PSR-15 middleware.
* Shared Desiderio template and partial paths for EXT:news and EXT:blog.
* Shared Solr defaults and numbered pagination partials.
* MCP table metadata for ``tt_address`` as ``Addresses``.

Thanks
======

Special thanks to Netresearch DTT GmbH for ``netresearch/t3-cowriter``, which
is used by the Cowriter RTE preset and Visual Editor preload middleware. Thanks
also to the TYPO3 core team and to the extension maintainers listed in the
root project README.
