..  _installation:

============
Installation
============

Install the extension through Composer from the repository root:

..  code-block:: bash
    :caption: Install dependencies

    composer install

The package requires TYPO3 14.3 or newer and PHP 8.2 or newer. TYPO3 13 support
has been removed.

In this lab, the package is wired as a path repository:

..  code-block:: bash
    :caption: Path repository install

    composer require webconsulting/site-package:^14.1

Run TYPO3 extension setup after dependency changes:

..  code-block:: bash
    :caption: Update TYPO3 extension state

    vendor/bin/typo3 extension:setup
    vendor/bin/typo3 cache:flush

Attach one or more Site Sets in the target site configuration under
:file:`config/sites/<site>/config.yaml`.
