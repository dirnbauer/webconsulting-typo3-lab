<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Creates the lab-owned WorkOS frontend demo tree with TYPO3 DataHandler.
 *
 * The command is intentionally idempotent: existing pages and content records
 * are updated in place, while missing records are created. This makes the demo
 * reproducible after importing a fresh database snapshot without bypassing TCA,
 * workspace handling, the reference index, or TYPO3 cache invalidation.
 */
#[AsCommand(
    name: 'sitepackage:seed-workos-frontend',
    description: 'Create or refresh the Desiderio WorkOS frontend plugin demo pages.',
)]
final class SeedWorkosFrontendDemoCommand extends Command
{
    private const ROOT_PAGE_UID = 505;
    private const WORKOS_FEATURE_PAGE_UID = 1075;

    private const STORAGE_SLUG = '/workos-frontend-users';
    private const OVERVIEW_SLUG = '/features/workos/frontend-plugins';
    private const LOGIN_SLUG = '/features/workos/frontend-plugins/login';
    private const ACCOUNT_SLUG = '/features/workos/frontend-plugins/account-center';
    private const TEAM_SLUG = '/features/workos/frontend-plugins/team-administration';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        Bootstrap::initializeBackendAuthentication();

        try {
            $storagePageUid = $this->ensurePage(
                self::ROOT_PAGE_UID,
                self::STORAGE_SLUG,
                [
                    'title' => 'WorkOS frontend users',
                    'doktype' => 254,
                    'hidden' => 0,
                    'sorting' => 4096,
                ],
            );
            $frontendGroupUid = $this->ensureFrontendGroup($storagePageUid);

            $overviewPageUid = $this->ensurePage(
                self::WORKOS_FEATURE_PAGE_UID,
                self::OVERVIEW_SLUG,
                [
                    'title' => 'WorkOS frontend plugins',
                    'nav_title' => 'Frontend plugins',
                    'doktype' => 1,
                    'hidden' => 0,
                    'nav_hide' => 0,
                    'sorting' => 256,
                    'seo_title' => 'WorkOS frontend plugins for TYPO3',
                    'description' => 'Try the three WorkOS frontend plugins in the Desiderio TYPO3 lab: login and registration, the Account Center and team administration.',
                ],
            );

            $loginPageUid = $this->ensurePage(
                $overviewPageUid,
                self::LOGIN_SLUG,
                [
                    'title' => 'Login and registration',
                    'nav_title' => 'Login & registration',
                    'doktype' => 1,
                    'hidden' => 0,
                    'nav_hide' => 0,
                    'sorting' => 256,
                    'seo_title' => 'WorkOS login and registration for TYPO3',
                    'description' => 'The WorkOS Login plugin lets visitors sign in with a password or an email code, register, or use a social identity provider.',
                ],
            );

            $accountPageUid = $this->ensurePage(
                $overviewPageUid,
                self::ACCOUNT_SLUG,
                [
                    'title' => 'Account center',
                    'nav_title' => 'Account center',
                    'doktype' => 1,
                    'hidden' => 0,
                    'nav_hide' => 0,
                    'sorting' => 512,
                    'seo_title' => 'WorkOS account center for TYPO3',
                    'description' => 'The WorkOS Account Center plugin lets users manage their profile, password, MFA factors, sessions and organisation memberships in TYPO3.',
                ],
            );

            $teamPageUid = $this->ensurePage(
                $overviewPageUid,
                self::TEAM_SLUG,
                [
                    'title' => 'Team administration',
                    'nav_title' => 'Team administration',
                    'doktype' => 1,
                    'hidden' => 0,
                    'nav_hide' => 0,
                    'sorting' => 768,
                    'seo_title' => 'WorkOS team administration for TYPO3',
                    'description' => 'Manage invitations to a WorkOS organisation and open signed Admin Portal sessions from the TYPO3 frontend with the team plugin.',
                ],
            );

            $this->orderRecords('pages', $overviewPageUid, [$loginPageUid, $accountPageUid, $teamPageUid]);

            $this->seedOverviewContent($overviewPageUid);
            $this->seedPluginPage(
                $loginPageUid,
                'Login and registration',
                '<p>The login form supports email and password, six-digit email codes without a password, self-service registration and social identity providers. After sign-in, it shows the linked WorkOS profile and a sign-out button.</p><p>Use this page to test the whole login flow, signed out and signed in, in the active Desiderio theme preset.</p>',
                'workosauth_login',
            );
            $this->seedPluginPage(
                $accountPageUid,
                'Account center',
                '<p>The Account Center gives signed-in users one place for their account. They can edit profile details, change their password, enrol or remove TOTP MFA, review sessions, revoke access and see their organisation memberships.</p><p>If no WorkOS identity is linked, the plugin shows a sign-in or account-linking screen instead of an empty dashboard.</p>',
                'workosauth_account',
            );
            $this->seedPluginPage(
                $teamPageUid,
                'Team administration',
                '<p>The team plugin is for organisation administrators. They can create, resend and revoke invitations, and switch between organisations. They can also open signed one-time Admin Portal links for SSO, Directory Sync, audit logs, domain verification and certificate renewal.</p><p>What each user sees depends on their WorkOS organisation memberships and permissions.</p>',
                'workosauth_team',
            );

            $io->success('WorkOS frontend demo created or refreshed.');
            $io->definitionList(
                ['Overview page' => sprintf('%d (%s)', $overviewPageUid, self::OVERVIEW_SLUG)],
                ['Login page' => sprintf('%d (%s)', $loginPageUid, self::LOGIN_SLUG)],
                ['Account page' => sprintf('%d (%s)', $accountPageUid, self::ACCOUNT_SLUG)],
                ['Team page' => sprintf('%d (%s)', $teamPageUid, self::TEAM_SLUG)],
                ['Frontend storage page' => (string)$storagePageUid],
                ['Frontend group' => (string)$frontendGroupUid],
            );
            $io->note([
                sprintf('TYPO3_WORKOS_FRONTEND_STORAGE_PID=%d', $storagePageUid),
                sprintf('TYPO3_WORKOS_FRONTEND_DEFAULT_GROUP_UIDS=%d', $frontendGroupUid),
            ]);

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * @param array<string, int|string> $fields
     */
    private function ensurePage(int $pid, string $slug, array $fields): int
    {
        $uid = $this->findPageBySlug($slug);
        $pageData = $fields + [
            'pid' => $pid,
            'slug' => $slug,
            'sys_language_uid' => 0,
        ];

        if ($uid !== null) {
            $this->applyData('pages', $uid, $pageData);
            return $uid;
        }

        return $this->createRecord('pages', $pageData);
    }

    private function ensureFrontendGroup(int $storagePageUid): int
    {
        $connection = $this->connectionPool->getConnectionForTable('fe_groups');
        $uid = $connection->select(
            ['uid'],
            'fe_groups',
            [
                'pid' => $storagePageUid,
                'title' => 'WorkOS frontend users',
                'deleted' => 0,
            ],
        )->fetchOne();

        $fields = [
            'pid' => $storagePageUid,
            'title' => 'WorkOS frontend users',
            'hidden' => 0,
        ];

        if (is_numeric($uid)) {
            $groupUid = (int)$uid;
            $this->applyData('fe_groups', $groupUid, $fields);
            return $groupUid;
        }

        return $this->createRecord('fe_groups', $fields);
    }

    private function seedOverviewContent(int $pageUid): void
    {
        $headerUid = $this->ensureContent(
            $pageUid,
            'desiderio_headersection',
            'WorkOS frontend plugins',
            [
                'eyebrow' => 'Authentication lab',
                'subheadline' => 'Three frontend plugins for sign-in, accounts and teams. TYPO3 renders them, and the semantic shadcn tokens of Desiderio style them.',
                'desiderio_headersection_variant' => 'center',
                'sorting' => 256,
            ],
        );
        $textUid = $this->ensureContent(
            $pageUid,
            'desiderio_textmedia',
            'One identity layer, three focused plugins',
            [
                'content' => '<p>The WorkOS extension handles authentication in one place and offers three frontend plugins:</p><ul><li><strong>Login and registration</strong> for passwords, Magic Auth, social sign-in and self-service sign-up.</li><li><strong>Account Center</strong> for profile, password, MFA, sessions and memberships.</li><li><strong>Team administration</strong> for invitations and signed WorkOS Admin Portal sessions.</li></ul><p>The lab changes only the presentation. Controllers, security checks, request tokens, WorkOS API calls and TYPO3 user provisioning still come from <code>webconsulting/workos-auth</code>.</p>',
                'sorting' => 512,
            ],
        );
        $menuUid = $this->ensureContent(
            $pageUid,
            'menu_subpages',
            'Explore the frontend plugins',
            [
                'subheader' => 'Each WorkOS frontend plugin has its own page.',
                'sorting' => 768,
            ],
        );
        $this->orderRecords('tt_content', $pageUid, [$headerUid, $textUid, $menuUid]);
    }

    private function seedPluginPage(int $pageUid, string $header, string $bodytext, string $pluginCType): void
    {
        $introUid = $this->ensureContent(
            $pageUid,
            'text',
            $header,
            [
                'bodytext' => $bodytext,
                'header_layout' => 2,
                'sorting' => 256,
            ],
        );
        $pluginUid = $this->ensureContent(
            $pageUid,
            $pluginCType,
            '',
            [
                'sorting' => 512,
            ],
        );
        $this->orderRecords('tt_content', $pageUid, [$introUid, $pluginUid]);
    }

    /**
     * @param list<int> $uids
     */
    private function orderRecords(string $table, int $parentUid, array $uids): void
    {
        $previousUid = null;
        foreach ($uids as $uid) {
            $target = $previousUid === null ? $parentUid : -$previousUid;
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start([], [$table => [$uid => ['move' => $target]]]);
            $dataHandler->process_cmdmap();
            if ($dataHandler->errorLog !== []) {
                throw new \RuntimeException(
                    'DataHandler ordering error: ' . $this->formatDataHandlerErrors($dataHandler),
                    1783716003,
                );
            }
            $previousUid = $uid;
        }
    }

    /**
     * @param array<string, int|string> $fields
     */
    private function ensureContent(int $pid, string $cType, string $header, array $fields): int
    {
        $connection = $this->connectionPool->getConnectionForTable('tt_content');
        $criteria = [
            'pid' => $pid,
            'CType' => $cType,
            'sys_language_uid' => 0,
            'deleted' => 0,
        ];
        if ($header !== '') {
            $criteria['header'] = $header;
        }

        $uid = $connection->select(['uid'], 'tt_content', $criteria)->fetchOne();
        $contentData = $fields + [
            'pid' => $pid,
            'CType' => $cType,
            'header' => $header,
            'colPos' => 0,
            'hidden' => 0,
            'sys_language_uid' => 0,
        ];

        if (is_numeric($uid)) {
            $contentUid = (int)$uid;
            $this->applyData('tt_content', $contentUid, $contentData);
            return $contentUid;
        }

        return $this->createRecord('tt_content', $contentData);
    }

    private function findPageBySlug(string $slug): ?int
    {
        $uid = $this->connectionPool->getConnectionForTable('pages')->select(
            ['uid'],
            'pages',
            [
                'slug' => $slug,
                'sys_language_uid' => 0,
                'deleted' => 0,
            ],
        )->fetchOne();

        return is_numeric($uid) ? (int)$uid : null;
    }

    /**
     * @param array<string, int|string> $fields
     */
    private function createRecord(string $table, array $fields): int
    {
        $newIdentifier = 'NEW' . bin2hex(random_bytes(8));
        $dataHandler = $this->runDataHandler([
            $table => [
                $newIdentifier => $fields,
            ],
        ]);
        $uid = $dataHandler->substNEWwithIDs[$newIdentifier] ?? null;
        if (!is_numeric($uid)) {
            throw new \RuntimeException(sprintf('TYPO3 did not return a UID for new %s record.', $table), 1783716001);
        }

        return (int)$uid;
    }

    /**
     * @param array<string, int|string> $fields
     */
    private function applyData(string $table, int $uid, array $fields): void
    {
        $this->runDataHandler([
            $table => [
                $uid => $fields,
            ],
        ]);
    }

    /**
     * @param array<string, array<int|string, array<string, int|string>>> $dataMap
     */
    private function runDataHandler(array $dataMap): DataHandler
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($dataMap, []);
        $dataHandler->process_datamap();
        if ($dataHandler->errorLog !== []) {
            throw new \RuntimeException(
                'DataHandler error: ' . $this->formatDataHandlerErrors($dataHandler),
                1783716002,
            );
        }

        return $dataHandler;
    }

    private function formatDataHandlerErrors(DataHandler $dataHandler): string
    {
        $errors = array_map(
            static function (mixed $error): string {
                if (is_string($error)) {
                    return $error;
                }
                if (is_scalar($error) || $error === null) {
                    return var_export($error, true);
                }
                return get_debug_type($error);
            },
            $dataHandler->errorLog,
        );

        return implode('; ', $errors);
    }
}
