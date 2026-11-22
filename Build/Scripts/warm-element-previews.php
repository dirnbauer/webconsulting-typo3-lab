<?php

/*
 * Warm Desiderio's element library previews where the site sits behind HTTP Basic Auth.
 *
 * desiderio:library:warm requests every preview URL through the site's public base. On the
 * production host Apache asks every request for the lab's Basic Auth (docker/coolify/
 * apache-vhost.conf), so the plain command gets 401 for all previews. This wrapper runs the same
 * command with one extra Guzzle middleware: when BASIC_AUTH_USER and BASIC_AUTH_PASSWORD are set
 * (the production web container has both), requests to the lab's own hosts carry them. Requests
 * to any other host are left alone, and nothing is written to a file.
 *
 * Without those variables (DDEV) it runs the command unchanged.
 *
 *   php -d memory_limit=3G Build/Scripts/warm-element-previews.php --site=desiderio
 *
 * Arguments are passed to desiderio:library:warm. WARM_BASIC_AUTH_HOSTS (comma-separated)
 * overrides the hosts that get the credentials; default typo3-lab.webconsulting.at.
 */

declare(strict_types=1);

use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use TYPO3\CMS\Core\Console\CommandApplication;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;

$projectRoot = dirname(__DIR__, 2);
$classLoader = require $projectRoot . '/vendor/autoload.php';
SystemEnvironmentBuilder::run(1, SystemEnvironmentBuilder::REQUESTTYPE_CLI);
$container = Bootstrap::init($classLoader, true);

$user = (string)getenv('BASIC_AUTH_USER');
$password = (string)getenv('BASIC_AUTH_PASSWORD');
if ($user !== '' && $password !== '') {
    $hostList = (string)(getenv('WARM_BASIC_AUTH_HOSTS') ?: 'typo3-lab.webconsulting.at');
    $hosts = array_values(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        explode(',', $hostList)
    )));
    $authorization = 'Basic ' . base64_encode($user . ':' . $password);
    $GLOBALS['TYPO3_CONF_VARS']['HTTP']['handler']['labOwnHostBasicAuth'] = Middleware::mapRequest(
        static fn (RequestInterface $request): RequestInterface => in_array(strtolower($request->getUri()->getHost()), $hosts, true)
            ? $request->withHeader('Authorization', $authorization)
            : $request
    );
}

$_SERVER['argv'] = array_merge(['typo3', 'desiderio:library:warm'], array_slice($_SERVER['argv'] ?? [], 1));
$_SERVER['argc'] = count($_SERVER['argv']);
$container->get(CommandApplication::class)->run();
