<?php

declare(strict_types=1);

/*
 * Creates a backend session for an existing backend user and prints the
 * be_typo_user cookie value, for the film captures (they run headless and
 * cannot log in through the form):
 *
 *   ddev exec php packages/site_package/Build/Film/backend-session.php <be_users.uid> > var/film/backend-session.txt
 *
 * Film with an English-speaking admin in the live workspace. The session is
 * not bound to an IP address; end it afterwards:
 *
 *   ddev mysql -e "DELETE FROM be_sessions WHERE ses_userid=<uid> AND ses_iplock='[DISABLED]'"
 */

use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Session\UserSessionManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$classLoader = require dirname(__DIR__, 4) . '/vendor/autoload.php';
SystemEnvironmentBuilder::run(0, SystemEnvironmentBuilder::REQUESTTYPE_CLI);
Bootstrap::init($classLoader);

$uid = (int)($argv[1] ?? 0);
if ($uid <= 0) {
    fwrite(STDERR, "Usage: backend-session.php <be_users.uid>\n");
    exit(1);
}
$manager = UserSessionManager::create('BE');
$session = $manager->elevateToFixatedUserSession($manager->createAnonymousSession(), $uid);
GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('be_sessions')
    ->update('be_sessions', ['ses_iplock' => '[DISABLED]'], ['ses_userid' => $uid]);
echo $session->getJwt(), "\n";
