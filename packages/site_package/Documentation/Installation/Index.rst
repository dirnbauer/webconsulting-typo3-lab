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

Run TYPO3 extension setup after dependency changes:

..  code-block:: bash
    :caption: Update TYPO3 extension state

    vendor/bin/typo3 extension:setup
    vendor/bin/typo3 cache:flush
