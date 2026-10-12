<?php

declare(strict_types=1);

/**
 * Exercise the Jev lab examples without submitting Powermail forms or sending mail.
 *
 * Run inside the configured TYPO3 runtime:
 *   php packages/site_package/Build/Scripts/check-jev-demo-options.php > /tmp/jev-results.jsonl
 * Optional: --language=en|de|zh|hu --group=examples|manual|fallback
 *
 * Uses the persisted lab decisions and their existing technical identity. Calls the
 * configured Jev service (normal run logs/cache may be written) and condition.json.
 * BASIC_AUTH_USER/PASSWORD are read from the runtime environment, never printed.
 * Refuses to run unless the mail transport remains the lab's mbox transport.
 */

use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use Webconsulting\WebconJev\Backend\Controller\PlaygroundApiController;
use Webconsulting\WebconJev\Client\Dto\DecisionResult;
use Webconsulting\WebconJev\Command\PingCommand;
use Webconsulting\WebconJev\Service\DecisionOutcome;
use Webconsulting\WebconJev\Service\RunLogger;

$root = dirname(__DIR__, 4);
$loader = require $root . '/vendor/autoload.php';
SystemEnvironmentBuilder::run(1, SystemEnvironmentBuilder::REQUESTTYPE_CLI);
$container = Bootstrap::init($loader);
if (($GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport'] ?? '') !== 'mbox') {
    throw new RuntimeException('Expected the lab mbox mail transport.');
}
$options = getopt('', ['language:', 'group:']);
$languages = ['en' => 0, 'de' => 1, 'zh' => 2, 'hu' => 3];
if (isset($options['language'])) {
    $language = (string)$options['language'];
    if (!isset($languages[$language])) {
        throw new InvalidArgumentException('Unknown language.');
    }
    $languages = [$language => $languages[$language]];
}
$group = (string)($options['group'] ?? 'all');
if (!in_array($group, ['all', 'examples', 'manual', 'fallback'], true)) {
    throw new InvalidArgumentException('Unknown group.');
}
$matrix = json_decode(file_get_contents($root . '/packages/site_package/Resources/Private/Data/Content/jev/test-matrix.json'), true, 512, JSON_THROW_ON_ERROR);
// Public container services hold the existing internal runner and repository.
$controller = $container->get(PlaygroundApiController::class);
$repository = (new ReflectionProperty($controller, 'decisions'))->getValue($controller);
$runner = (new ReflectionProperty($controller, 'runner'))->getValue($controller);
$ping = $container->get(PingCommand::class);
$actor = (new ReflectionProperty($ping, 'technicalActor'))->getValue($ping);
$provisioner = (new ReflectionProperty($ping, 'provisioner'))->getValue($ping);
$actorUid = $provisioner->resolve();
if ($actorUid <= 0) {
    throw new RuntimeException('The existing technical identity is unavailable.');
}
$cases = [];
if ($group === 'all' || $group === 'examples') {
    $cases = $matrix['cases'];
}
if ($group === 'all' || $group === 'manual') {
    $cases = array_merge($cases, $matrix['manual_option_cases']);
}
$failed = 0;
foreach ($cases as $case) {
    foreach ($languages as $language => $languageId) {
        $config = $matrix['forms'][$case['slug']];
        $decision = $repository->findByUid($config['decision'], $languageId);
        if ($decision === null) {
            throw new RuntimeException('Expected lab decision is missing.');
        }
        $fields = $case['fields_by_language'][$language];
        $outcome = $actor->runAs($actorUid, fn() => $runner->run($decision, ['field' => $fields], RunLogger::CONTEXT_CLI, 'Jev lab matrix ' . $case['id']));
        $row = ['case' => $case['id'], 'language' => $language, 'slug' => $case['slug'], 'fields' => $fields, 'summary' => $outcome->summary(), 'route' => $outcome->outcomeFor($config['question']), 'default' => $outcome->usedDefaultFor($config['question']), 'result' => $outcome->result->toArray(), 'failures' => []];
        if ($row['route'] !== $case['expected_route']) {
            $row['failures'][] = 'route';
        }
        foreach ($case['expected_answers'] as $question => $value) {
            if ($outcome->answer($question)?->value() !== $value) {
                $row['failures'][] = 'answer.' . $question;
            }
        }
        foreach ($case['expected_score_ranges'] ?? [] as $question => [$minimum, $maximum]) {
            $value = $outcome->answer($question)?->value();
            if (!is_numeric($value) || $value < $minimum || $value > $maximum) {
                $row['failures'][] = 'score.' . $question;
            }
        }
        if ($case['expected_actions'] !== []) {
            $url = $matrix['condition_urls'][$language][$case['slug']];
            $headers = "Content-Type: application/x-www-form-urlencoded\r\n";
            $basicUser = getenv('BASIC_AUTH_USER');
            if (is_string($basicUser) && $basicUser !== '') {
                $headers .= 'Authorization: Basic ' . base64_encode($basicUser . ':' . getenv('BASIC_AUTH_PASSWORD')) . "\r\n";
            }
            $context = stream_context_create(['http' => ['method' => 'POST', 'header' => $headers, 'content' => http_build_query(['tx_powermail_pi1' => ['mail' => ['form' => $config['form']], 'field' => $fields]]), 'timeout' => 40, 'ignore_errors' => true]]);
            $raw = file_get_contents($url, false, $context);
            $data = json_decode($raw === false ? '' : $raw, true);
            $row['http_status'] = $http_response_header[0] ?? null;
            $actions = [];
            foreach ($data['todo'] ?? [] as $fieldsets) {
                foreach ($fieldsets as $formFields) {
                    foreach ($formFields as $marker => $value) {
                        $actions[$marker] = $value['#action'] ?? null;
                    }
                }
            }
            $row['actions'] = $actions;
            foreach ($case['expected_actions'] as $field => $expected) {
                if (($actions[$field] ?? null) !== $expected) {
                    $row['failures'][] = 'action.' . $field;
                }
            }
        }
        $failed += $row['failures'] !== [] ? 1 : 0;
        echo json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }
}
if ($group === 'all' || $group === 'fallback') {
    foreach ($matrix['forms'] as $slug => $config) {
        $decision = $repository->findByUid($config['decision']);
        $outcome = new DecisionOutcome($decision, DecisionResult::fallback('Synthetic QA service-failure fixture'));
        $pass = $outcome->outcomeFor($config['question']) === $decision->defaultOutcome && $outcome->usedDefaultFor($config['question']);
        $failed += $pass ? 0 : 1;
        echo json_encode(['case' => 'fallback_' . $slug, 'kind' => 'deterministic_fixture', 'route' => $outcome->outcomeFor($config['question']), 'default' => $outcome->usedDefaultFor($config['question']), 'pass' => $pass], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }
}
exit($failed > 0 ? 1 : 0);
