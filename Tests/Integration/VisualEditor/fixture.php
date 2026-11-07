<?php

/**
 * Disposable fixtures for the regular-runtime Visual Editor suite (see README.md).
 *
 * Runs inside the DDEV web container from the project root, normally through
 * Build/Scripts/ve-runtime.sh, which copies this file to /tmp/ve-runtime.php.
 * Every record it creates lives on one new page below showcase 8430, in one
 * disposable workspace, or is a disposable backend user/group. No existing
 * user's password, preferences or workspace is touched.
 *
 * State: var/ve-integration/fixture.json (0600, container only; the wrapper
 * copies it out). Sessions: var/ve-integration/state-<role>.json (Playwright
 * storageState with a fixated backend session; no password is ever stored or
 * printed - users get a random one that is discarded at once).
 *
 * Modes: inspect | fixture [run] | extra | visible | localize | ve_editor |
 * session <role> | settings <role> <chooserMode> <workspace> [labels] [layout] [colorScheme] |
 * firstversion <0|1> | version | cleanup
 */

declare(strict_types=1);

use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Session\UserSessionManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$loader = require getcwd() . '/vendor/autoload.php';
SystemEnvironmentBuilder::run(1, SystemEnvironmentBuilder::REQUESTTYPE_CLI);
Bootstrap::init($loader);
Bootstrap::loadExtTables();
Bootstrap::initializeBackendUser(TYPO3\CMS\Core\Authentication\CommandLineUserAuthentication::class);
Bootstrap::initializeBackendAuthentication();

const STATE_DIR = 'var/ve-integration';
const STATE = STATE_DIR . '/fixture.json';
const SHOWCASE = 8430;
const ROLES = ['admin', 'editor', 'veEditor'];
/** Child tables of the fixture's collections (the restricted editor's tables_modify, minus legal links). */
const CHILD_TABLES = ['feature_checklist_items', 'tabs_items', 'data_table_rows', 'data_table_cells', 'data_table_columns'];

$pool = GeneralUtility::makeInstance(ConnectionPool::class);

function dh(array $data, array $cmd = [], int $workspace = 0): DataHandler
{
    $GLOBALS['BE_USER']->workspace = $workspace;
    $handler = GeneralUtility::makeInstance(DataHandler::class);
    $handler->start($data, $cmd);
    $handler->process_datamap();
    $handler->process_cmdmap();
    $GLOBALS['BE_USER']->workspace = 0;
    if ($handler->errorLog) {
        throw new RuntimeException(json_encode($handler->errorLog));
    }
    return $handler;
}

function loadState(): array
{
    return json_decode((string)file_get_contents(STATE), true, 512, JSON_THROW_ON_ERROR);
}

function saveState(array $state): void
{
    file_put_contents(STATE, json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    chmod(STATE, 0600);
}

function pageTsconfig(int $firstVersion): string
{
    return "tx_visualeditorenhancements.inlineRecords.enabled = 1\n"
        . "tx_visualeditorenhancements.inlineRecords.firstVersion = {$firstVersion}\n";
}

/** A password nobody knows: the suite signs in through fixated sessions only. */
function throwawayPassword(): string
{
    return bin2hex(random_bytes(24)) . '!Aa9';
}

function userSettings(string $chooserMode = 'tabs', string $labels = 'caption', string $layout = 'inspector', string $colorScheme = 'auto'): string
{
    return serialize([
        'colorScheme' => $colorScheme,
        'tx_visualeditor_inlineRecords' => 1,
        'tx_visualeditor_fieldChooserMode' => $chooserMode,
        'tx_visualeditor_showLibrary' => 1,
        'tx_visualeditor_showContextButtons' => 1,
        'tx_visualeditor_actionLabels' => $labels,
        'tx_visualeditor_entriesLayout' => $layout,
    ]);
}

$mode = $argv[1] ?? 'inspect';

if ($mode === 'inspect') {
    foreach (['desiderio_tabs', 'desiderio_pricingthreetier', 'desiderio_gallery', 'desiderio_featurelist', 'desiderio_datatable'] as $type) {
        foreach ($GLOBALS['TCA']['tt_content']['types'][$type]['columnsOverrides'] ?? [] as $field => $override) {
            $config = array_replace($GLOBALS['TCA']['tt_content']['columns'][$field]['config'] ?? [], $override['config'] ?? []);
            if (in_array($config['type'] ?? '', ['inline', 'file'], true)) {
                echo json_encode([$type, $field, $config]), "\n";
            }
        }
    }
    exit;
}

if ($mode === 'fixture') {
    if (file_exists(STATE)) {
        throw new RuntimeException('A fixture state exists; run cleanup first.');
    }
    if (!is_dir(STATE_DIR)) {
        mkdir(STATE_DIR, 0700, true);
    }
    $run = preg_replace('/\D/', '', $argv[2] ?? date('YmdHi'));
    $tables = array_keys($GLOBALS['TCA']);
    $excludes = [];
    foreach ($GLOBALS['TCA'] as $table => $tca) {
        foreach ($tca['columns'] ?? [] as $field => $column) {
            if ($column['exclude'] ?? false) {
                $excludes[] = "$table:$field";
            }
        }
    }
    $handler = dh([
        'pages' => ['NEWpage' => [
            'pid' => SHOWCASE, 'title' => 'VEE disposable integration ' . $run,
            'slug' => '/content-types/visual-editor-inline/integration-' . $run,
            'nav_hide' => 1, 'no_search' => 1, 'hidden' => 0, 'perms_everybody' => 31, 'TSconfig' => pageTsconfig(0),
        ]],
        'be_groups' => ['NEWgroup' => [
            'pid' => 0, 'title' => 'VEE disposable integration ' . $run,
            'groupMods' => 'web_edit,web_layout,web_list,user_setup',
            'tables_select' => implode(',', $tables),
            'tables_modify' => implode(',', array_diff($tables, ['be_users', 'be_groups', 'sys_workspace'])),
            'non_exclude_fields' => implode(',', $excludes),
            'explicit_allowdeny' => 'tt_content:CType:*:ALLOW', 'allowed_languages' => '0,1,2,3', 'workspace_perms' => 1,
        ]],
        'be_users' => ['NEWadmin' => [
            'pid' => 0, 'username' => 'vee_integration_admin_' . $run, 'password' => throwawayPassword(),
            'admin' => 1, 'disable' => 0, 'lang' => 'en',
        ]],
    ]);
    $page = $handler->substNEWwithIDs['NEWpage'];
    $group = $handler->substNEWwithIDs['NEWgroup'];
    $admin = $handler->substNEWwithIDs['NEWadmin'];
    $handler = dh([
        'be_groups' => [$group => ['db_mountpoints' => (string)$page]],
        'be_users' => ['NEWeditor' => [
            'pid' => 0, 'username' => 'vee_integration_editor_' . $run, 'password' => throwawayPassword(),
            'admin' => 0, 'disable' => 0, 'usergroup' => (string)$group, 'lang' => 'de',
        ]],
    ]);
    $editor = $handler->substNEWwithIDs['NEWeditor'];
    $handler = dh(['sys_workspace' => ['NEWworkspace' => [
        // sys_workspace.title holds 30 characters.
        'pid' => 0, 'title' => 'VEE integration ' . $run,
        'adminusers' => 'be_users_' . $admin, 'members' => 'be_users_' . $editor,
    ]]]);
    $workspace = $handler->substNEWwithIDs['NEWworkspace'];
    foreach ([$admin, $editor] as $id) {
        $pool->getConnectionForTable('be_users')->update('be_users', ['uc' => userSettings()], ['uid' => $id]);
    }
    $handler = dh([
        'tt_content' => [
            'NEWtabs' => ['pid' => $page, 'CType' => 'desiderio_tabs', 'header' => 'VEE disposable tabs', 'desiderio_tabs_items' => 'NEWone,NEWtwo'],
            'NEWlist' => ['pid' => $page, 'CType' => 'desiderio_featurelist', 'header' => 'VEE disposable list'],
        ],
        'tabs_items' => [
            'NEWone' => ['pid' => $page, 'tab_label' => 'Integration One', 'tab_content' => '<p>First disposable panel</p>'],
            'NEWtwo' => ['pid' => $page, 'tab_label' => 'Integration Two', 'tab_content' => '<p>Second disposable panel</p>'],
        ],
    ]);
    $state = ['run' => $run, 'page' => $page, 'group' => $group, 'admin' => $admin, 'editor' => $editor,
        'workspace' => $workspace, 'records' => $handler->substNEWwithIDs];
    saveState($state);
    echo json_encode($state, JSON_PRETTY_PRINT), "\n";
    exit;
}

$state = loadState();
$page = (int)$state['page'];

if ($mode === 'extra') {
    $handler = dh([
        'tt_content' => [
            'NEWcheck' => ['pid' => $page, 'CType' => 'desiderio_featurechecklist', 'header' => 'VEE disposable checklist', 'desiderio_featurechecklist_items' => 'NEWalpha,NEWbeta,NEWgamma'],
            'NEWlegal' => ['pid' => $page, 'CType' => 'desiderio_legallinks', 'header' => 'VEE disposable legal', 'desiderio_legallinks_link_items' => 'NEWlegalitem'],
            'NEWtable' => ['pid' => $page, 'CType' => 'desiderio_datatable', 'header' => 'VEE disposable nested table', 'desiderio_datatable_column_definitions' => 'NEWcol', 'desiderio_datatable_rows' => 'NEWrow'],
            'NEWshared' => ['pid' => $page, 'CType' => 'desiderio_heroproduct', 'header' => 'VEE disposable shared'],
            // Never versioned and never translated: the first-version scenario's element.
            'NEWfirst' => ['pid' => $page, 'CType' => 'desiderio_featurechecklist', 'header' => 'VEE disposable first version', 'desiderio_featurechecklist_items' => 'NEWfirstone,NEWfirsttwo'],
            // Localized in mode `localize`; kept apart so translations never touch the workspace cases.
            'NEWtrans' => ['pid' => $page, 'CType' => 'desiderio_featurechecklist', 'header' => 'VEE disposable translated', 'desiderio_featurechecklist_items' => 'NEWtransone,NEWtranstwo,NEWtransthree'],
        ],
        'feature_checklist_items' => [
            'NEWalpha' => ['pid' => $page, 'title' => 'Alpha entry'],
            'NEWbeta' => ['pid' => $page, 'title' => 'Beta entry'],
            'NEWgamma' => ['pid' => $page, 'title' => 'Gamma entry'],
            'NEWfirstone' => ['pid' => $page, 'title' => 'First version one'],
            'NEWfirsttwo' => ['pid' => $page, 'title' => 'First version two'],
            'NEWtransone' => ['pid' => $page, 'title' => 'Translated one'],
            'NEWtranstwo' => ['pid' => $page, 'title' => 'Translated two'],
            'NEWtransthree' => ['pid' => $page, 'title' => 'Translated three'],
        ],
        'legal_links_link_items' => ['NEWlegalitem' => ['pid' => $page, 'label' => 'Baseline legal']],
        'data_table_columns' => ['NEWcol' => ['pid' => $page, 'column_label' => 'Column']],
        'data_table_rows' => ['NEWrow' => ['pid' => $page, 'row_label' => 'Row', 'cells' => 'NEWcell']],
        'data_table_cells' => ['NEWcell' => ['pid' => $page, 'value' => 'Cell']],
    ]);
    $state['records'] = array_merge($state['records'], $handler->substNEWwithIDs);
    saveState($state);
    $types = array_keys($GLOBALS['TCA']['tt_content']['types']);
    dh(['be_groups' => [$state['group'] => ['explicit_allowdeny' => implode(',', array_map(static fn(string $type): string => 'tt_content:CType:' . $type, $types))]]]);
    echo json_encode($handler->substNEWwithIDs), "\n";
    exit;
}

if ($mode === 'visible') {
    $data = [];
    foreach (['tt_content', 'feature_checklist_items', 'tabs_items', 'legal_links_link_items', 'data_table_rows', 'data_table_cells', 'data_table_columns'] as $table) {
        foreach ($pool->getConnectionForTable($table)->fetchFirstColumn("SELECT uid FROM $table WHERE pid=? AND deleted=0 AND t3ver_wsid=0", [$page]) as $uid) {
            $data[$table][(int)$uid] = ['hidden' => 0];
        }
    }
    dh($data);
    echo "Fixture records visible\n";
    exit;
}

if ($mode === 'localize') {
    // Core localize of the translated checklist into language 1 (German),
    // then Core's inline synchronization for two of its three entries, so one
    // entry is missing in German for the sync scenario.
    $trans = (int)$state['records']['NEWtrans'];
    dh([], ['pages' => [$page => ['localize' => 1]]]);
    dh([], ['tt_content' => [$trans => ['localize' => 1]]]);
    $translated = (int)$pool->getConnectionForTable('tt_content')->fetchOne(
        'SELECT uid FROM tt_content WHERE l18n_parent=? AND sys_language_uid=1 AND deleted=0 AND t3ver_wsid=0',
        [$trans]
    );
    if ($translated === 0) {
        throw new RuntimeException('Core did not localize the translated checklist');
    }
    $children = $pool->getConnectionForTable('feature_checklist_items')->fetchAllAssociative(
        'SELECT uid, sys_language_uid, l10n_parent FROM feature_checklist_items WHERE foreign_table_parent_uid=? AND deleted=0 AND t3ver_wsid=0',
        [$translated]
    );
    if ($children === []) {
        dh([], ['tt_content' => [$trans => ['inlineLocalizeSynchronize' => [
            'field' => 'desiderio_featurechecklist_items', 'language' => 1,
            'ids' => [(int)$state['records']['NEWtransone'], (int)$state['records']['NEWtranstwo']],
        ]]]]);
    }
    $state['translated'] = [
        'page' => (int)$pool->getConnectionForTable('pages')->fetchOne('SELECT uid FROM pages WHERE l10n_parent=? AND sys_language_uid=1 AND deleted=0', [$page]),
        'trans' => $translated,
        'children' => array_map('intval', $pool->getConnectionForTable('feature_checklist_items')->fetchFirstColumn(
            'SELECT uid FROM feature_checklist_items WHERE foreign_table_parent_uid=? AND sys_language_uid=1 AND deleted=0 AND t3ver_wsid=0 ORDER BY sorting',
            [$translated]
        )),
    ];
    saveState($state);
    echo json_encode($state['translated']), "\n";
    exit;
}

if ($mode === 've_editor') {
    // The plan's restricted editor (D12, fixture-only): content and the
    // fixture's child tables only (legal links deliberately NOT modifiable),
    // the tested CTypes only, the inline fields and the hidden right of every
    // child table except tabs_items, the page module of the Visual Editor,
    // the fixture page as its only mount, live + disposable workspace.
    if (isset($state['veEditor'])) {
        throw new RuntimeException('ve_editor exists');
    }
    $inline = ['desiderio_featurechecklist_items', 'desiderio_tabs_items', 'desiderio_legallinks_link_items',
        'desiderio_datatable_rows', 'desiderio_datatable_column_definitions'];
    $nonExclude = array_map(static fn(string $field): string => 'tt_content:' . $field, $inline);
    foreach (array_diff(CHILD_TABLES, ['tabs_items']) as $table) {
        $nonExclude[] = $table . ':hidden';
    }
    $nonExclude[] = 'data_table_rows:cells';
    $handler = dh(['be_groups' => ['NEWve' => [
        'pid' => 0, 'title' => 'VEE ve_editor ' . $state['run'],
        'groupMods' => 'web_edit', 'db_mountpoints' => (string)$page,
        'tables_select' => implode(',', array_merge(['pages', 'tt_content', 'legal_links_link_items'], CHILD_TABLES)),
        'tables_modify' => implode(',', array_merge(['tt_content'], CHILD_TABLES)),
        'non_exclude_fields' => implode(',', $nonExclude),
        'explicit_allowdeny' => implode(',', array_map(static fn(string $type): string => 'tt_content:CType:' . $type,
            ['desiderio_featurechecklist', 'desiderio_tabs', 'desiderio_legallinks', 'desiderio_datatable'])),
        'allowed_languages' => '0,1', 'workspace_perms' => 1,
    ]]]);
    $group = $handler->substNEWwithIDs['NEWve'];
    $handler = dh(['be_users' => ['NEWveeditor' => [
        'pid' => 0, 'username' => 'vee_integration_ve_editor_' . $state['run'], 'password' => throwawayPassword(),
        'admin' => 0, 'disable' => 0, 'usergroup' => (string)$group, 'lang' => 'en',
    ]]]);
    $user = $handler->substNEWwithIDs['NEWveeditor'];
    $members = (string)$pool->getConnectionForTable('sys_workspace')->fetchOne('SELECT members FROM sys_workspace WHERE uid=?', [$state['workspace']]);
    dh(['sys_workspace' => [$state['workspace'] => ['members' => ltrim($members . ',be_users_' . $user, ',')]]]);
    $pool->getConnectionForTable('be_users')->update('be_users', ['uc' => userSettings()], ['uid' => $user]);
    $state['veEditor'] = $user;
    $state['veGroup'] = $group;
    saveState($state);
    echo json_encode(['veEditor' => $user, 'veGroup' => $group]), "\n";
    exit;
}

if ($mode === 'session') {
    $role = $argv[2] ?? '';
    $uid = in_array($role, ROLES, true) ? (int)($state[$role] ?? 0) : 0;
    if ($uid === 0) {
        throw new RuntimeException('Fixture identity only');
    }
    $manager = UserSessionManager::create('BE');
    $session = $manager->elevateToFixatedUserSession($manager->createAnonymousSession(), $uid);
    $pool->getConnectionForTable('be_sessions')->update('be_sessions', ['ses_iplock' => '[DISABLED]'], ['ses_userid' => $uid]);
    $file = STATE_DIR . '/state-' . $role . '.json';
    file_put_contents($file, json_encode(['cookies' => [[
        'name' => $GLOBALS['TYPO3_CONF_VARS']['BE']['cookieName'] ?? 'be_typo_user',
        'value' => $session->getJwt(), 'domain' => 'webconsulting-typo3-lab.ddev.site', 'path' => '/',
        'expires' => -1, 'httpOnly' => true, 'secure' => true, 'sameSite' => 'Strict',
    ]], 'origins' => []], JSON_THROW_ON_ERROR));
    chmod($file, 0600);
    echo "Session state written for $role\n";
    exit;
}

if ($mode === 'settings') {
    $role = $argv[2] ?? '';
    $uid = in_array($role, ROLES, true) ? (int)($state[$role] ?? 0) : 0;
    if ($uid === 0) {
        throw new RuntimeException('Fixture identity only');
    }
    $pool->getConnectionForTable('be_users')->update('be_users', [
        'uc' => userSettings($argv[3] ?? 'tabs', $argv[5] ?? 'caption', $argv[6] ?? 'inspector', $argv[7] ?? 'auto'),
        'workspace_id' => (int)($argv[4] ?? 0),
    ], ['uid' => $uid]);
    echo "Fixture preferences set\n";
    exit;
}

if ($mode === 'firstversion') {
    dh(['pages' => [$page => ['TSconfig' => pageTsconfig(($argv[2] ?? '0') === '1' ? 1 : 0)]]]);
    echo 'firstVersion = ', ($argv[2] ?? '0') === '1' ? 1 : 0, "\n";
    exit;
}

if ($mode === 'version') {
    // Complete Core drafts of the checklist and ITS entries only (the
    // first-version and translated elements must stay unversioned).
    $check = (int)$state['records']['NEWcheck'];
    $workspace = (int)$state['workspace'];
    $content = $pool->getConnectionForTable('tt_content');
    if (!$content->count('*', 'tt_content', ['t3ver_oid' => $check, 't3ver_wsid' => $workspace])) {
        dh([], ['tt_content' => [$check => ['version' => ['action' => 'new', 'treeLevels' => 0]]]], $workspace);
    }
    $items = $pool->getConnectionForTable('feature_checklist_items');
    foreach ($items->fetchFirstColumn('SELECT uid FROM feature_checklist_items WHERE foreign_table_parent_uid=? AND t3ver_wsid=0 AND deleted=0', [$check]) as $uid) {
        if (!$items->count('*', 'feature_checklist_items', ['t3ver_oid' => $uid, 't3ver_wsid' => $workspace])) {
            dh([], ['feature_checklist_items' => [$uid => ['version' => ['action' => 'new', 'treeLevels' => 0]]]], $workspace);
        }
    }
    echo "Core-created complete draft fixtures\n";
    exit;
}

if ($mode === 'cleanup') {
    $workspace = (int)$state['workspace'];
    $GLOBALS['BE_USER']->workspace = $workspace;
    // Discard every draft of this run (parents first; Core discards their inline drafts with them).
    foreach (array_merge(['tt_content'], CHILD_TABLES, ['legal_links_link_items']) as $table) {
        foreach ($pool->getConnectionForTable($table)->fetchFirstColumn("SELECT uid FROM $table WHERE pid=? AND t3ver_wsid=? AND t3ver_oid>0", [$page, $workspace]) as $uid) {
            if ($pool->getConnectionForTable($table)->count('*', $table, ['uid' => $uid])) {
                dh([], [$table => [(int)$uid => ['discard' => 1]]], $workspace);
            }
        }
    }
    $GLOBALS['BE_USER']->workspace = 0;
    $users = array_values(array_filter([(int)$state['admin'], (int)$state['editor'], (int)($state['veEditor'] ?? 0)]));
    $groups = array_values(array_filter([(int)$state['group'], (int)($state['veGroup'] ?? 0)]));
    $cmd = ['pages' => [$page => ['delete' => 1]], 'sys_workspace' => [$workspace => ['delete' => 1]]];
    $data = [];
    foreach ($users as $uid) {
        $data['be_users'][$uid] = ['disable' => 1];
        $cmd['be_users'][$uid] = ['delete' => 1];
    }
    foreach ($groups as $uid) {
        $cmd['be_groups'][$uid] = ['delete' => 1];
    }
    dh($data, $cmd);
    // No credential survives: drop the disposable accounts' rows and sessions outright.
    $pool->getConnectionForTable('be_sessions')->executeStatement(
        'DELETE FROM be_sessions WHERE ses_userid IN (' . implode(',', $users) . ')'
    );
    $pool->getConnectionForTable('be_users')->executeStatement(
        'DELETE FROM be_users WHERE deleted=1 AND uid IN (' . implode(',', $users) . ')'
    );
    $pool->getConnectionForTable('be_groups')->executeStatement(
        'DELETE FROM be_groups WHERE deleted=1 AND uid IN (' . implode(',', $groups) . ')'
    );
    $remaining = [];
    $pages = array_filter([$page, (int)($state['translated']['page'] ?? 0)]);
    foreach ($GLOBALS['TCA'] as $table => $tca) {
        if (empty($tca['ctrl']['delete'])) {
            continue;
        }
        $connection = $pool->getConnectionForTable($table);
        if (!isset($connection->createSchemaManager()->listTableColumns($table)['pid'])) {
            continue;
        }
        $count = (int)$connection->fetchOne(
            "SELECT COUNT(*) FROM $table WHERE pid IN (" . implode(',', $pages) . ') AND ' . $tca['ctrl']['delete'] . '=0'
        );
        if ($count) {
            $remaining[$table] = $count;
        }
    }
    $proof = [
        'page' => $page,
        'pageDeleted' => (int)$pool->getConnectionForTable('pages')->fetchOne('SELECT COUNT(*) FROM pages WHERE (uid=? OR l10n_parent=?) AND deleted=0', [$page, $page]) === 0,
        'remainingActivePageOwnedRows' => (object)$remaining,
        'remainingDrafts' => (int)$pool->getConnectionForTable('tt_content')->fetchOne('SELECT COUNT(*) FROM tt_content WHERE t3ver_wsid=?', [$workspace])
            + array_sum(array_map(static fn(string $t): int => (int)$pool->getConnectionForTable($t)->fetchOne("SELECT COUNT(*) FROM $t WHERE t3ver_wsid=?", [$workspace]), CHILD_TABLES)),
        'disposableUsers' => (int)$pool->getConnectionForTable('be_users')->fetchOne('SELECT COUNT(*) FROM be_users WHERE uid IN (' . implode(',', $users) . ')'),
        'disposableGroups' => (int)$pool->getConnectionForTable('be_groups')->fetchOne('SELECT COUNT(*) FROM be_groups WHERE uid IN (' . implode(',', $groups) . ')'),
        'sessions' => (int)$pool->getConnectionForTable('be_sessions')->fetchOne('SELECT COUNT(*) FROM be_sessions WHERE ses_userid IN (' . implode(',', $users) . ')'),
        'workspaceDeleted' => (int)$pool->getConnectionForTable('sys_workspace')->fetchOne('SELECT deleted FROM sys_workspace WHERE uid=?', [$workspace]) === 1,
    ];
    foreach (glob(STATE_DIR . '/state-*.json') ?: [] as $file) {
        unlink($file);
    }
    unlink(STATE);
    echo json_encode($proof, JSON_PRETTY_PRINT), "\n";
    exit;
}

throw new RuntimeException('Unknown mode ' . $mode);
