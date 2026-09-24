<?php

declare(strict_types=1);

defined('TYPO3') or die();

// The Desiderio success stories keep their blog tags on the blog page itself
// (/success-stories, a standard page), not in a sysfolder. EXT:blog allows its
// tags in sysfolders only, so DataHandler refused every write there: editors
// could not edit a tag, and the German, Chinese and Hungarian tag names
// (sitepackage:content:translate) could not be created.
$GLOBALS['TCA']['tx_blog_domain_model_tag']['ctrl']['security']['ignorePageTypeRestriction'] = true;
