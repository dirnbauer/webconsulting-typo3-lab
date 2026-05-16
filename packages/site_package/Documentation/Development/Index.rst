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
