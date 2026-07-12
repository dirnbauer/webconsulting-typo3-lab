..  _development:

===========
Development
===========

Static analysis
===============

Run the TYPO3-aware PHPStan configuration at maximum level:

..  code-block:: bash
    :caption: Run PHPStan

    Build/Scripts/runTests.sh -s phpstan

Full local quality check
========================

Run the same checks as GitHub Actions:

..  code-block:: bash
    :caption: Run quality suite

    Build/Scripts/runTests.sh -s ci

The suite validates Composer metadata, PHP syntax, TYPO3 YAML files, and
PHPStan at maximum level.

YAML linting
============

Validate Site Set and site configuration YAML from the repository root:

..  code-block:: bash
    :caption: Lint YAML

    vendor/bin/typo3 lint:yaml config/sites packages/site_package/Configuration/Sets

Release metadata
================

For TYPO3 14.3+ classic-mode installations, keep these fields aligned in
:file:`composer.json`:

* ``extra.typo3/cms.extension-key``
* ``extra.typo3/cms.version``
* ``extra.typo3/cms.Package.providesPackages``

The extension version in Composer metadata must match the release tag used for
the package, for example ``14.3.4`` for tag ``v14.3.4`` when the package is
released independently, or the lab repository tag when the package ships as part
of the TYPO3 Lab monorepo. The current package metadata is ``14.3.4``.
