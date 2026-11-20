<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\AiStack;

use PHPUnit\Framework\TestCase;
use Webconsulting\SitePackage\AiStack\ToolDescriptor;
use Webconsulting\SitePackage\AiStack\ToolPolicy;

final class ToolPolicyTest extends TestCase
{
    /** @return list<ToolDescriptor> */
    private static function tools(): array
    {
        return [
            new ToolDescriptor('get_page_content', 'content', true, false),
            new ToolDescriptor('ask_choice', 'content', false, false),
            new ToolDescriptor('get_env_raw', 'system', false, false),
            new ToolDescriptor('list_be_users_raw', 'accounts', false, false),
            new ToolDescriptor('update_page_metadata', 'editing', false, true),
            new ToolDescriptor('fetch_external_url', 'web', false, true),
            new ToolDescriptor('adopt_client_file', 'files', false, true),
            new ToolDescriptor('mcp_deepwiki_read_wiki_contents', 'mcp', false, false, true),
        ];
    }

    public function testReadersKeepTheirDefaultAndTheChatReadersAreSwitchedOn(): void
    {
        $plan = (new ToolPolicy())->plan(self::tools(), [], []);

        self::assertArrayNotHasKey('get_page_content', $plan['tools'], 'a reader that ships on needs no row');
        self::assertTrue($plan['tools']['ask_choice']);
        self::assertArrayNotHasKey('get_env_raw', $plan['tools'], 'raw readers stay off');
        self::assertArrayNotHasKey('list_be_users_raw', $plan['tools']);
    }

    public function testOnlyListedWritersWithAnApprovalStepAreSwitchedOn(): void
    {
        $plan = (new ToolPolicy())->plan(self::tools(), [], []);

        self::assertTrue($plan['tools']['update_page_metadata']);
        self::assertTrue($plan['tools']['fetch_external_url']);
        self::assertArrayNotHasKey('adopt_client_file', $plan['tools'], 'an unlisted writer keeps its default (off)');
        self::assertSame([], $plan['refused']);
    }

    public function testAnUnlistedWriterSwitchedOnByHandIsSwitchedOff(): void
    {
        $plan = (new ToolPolicy())->plan(self::tools(), ['adopt_client_file' => true], []);

        self::assertFalse($plan['tools']['adopt_client_file']);
    }

    public function testAListedWriterThatLostItsApprovalStepIsRefused(): void
    {
        $tools = [new ToolDescriptor('update_page_metadata', 'editing', false, false)];
        $plan = (new ToolPolicy())->plan($tools, [], []);

        self::assertSame(['update_page_metadata'], $plan['refused']);
        self::assertArrayNotHasKey('update_page_metadata', $plan['tools'], 'treated as a reader: it keeps its default (off)');
    }

    public function testRemoteToolsAreLeftToTheirServerRecord(): void
    {
        $plan = (new ToolPolicy())->plan(self::tools(), ['mcp_deepwiki_read_wiki_contents' => true], []);

        self::assertArrayNotHasKey('mcp_deepwiki_read_wiki_contents', $plan['tools']);
    }

    public function testADisabledGroupOfAWantedToolIsSwitchedOn(): void
    {
        $plan = (new ToolPolicy())->plan(self::tools(), [], ['editing' => false, 'accounts' => false]);

        self::assertSame(['editing' => true], $plan['groups'], 'only groups of tools that should be on');
    }

    public function testASecondRunPlansNothing(): void
    {
        $policy = new ToolPolicy();
        $first = $policy->plan(self::tools(), [], ['editing' => false]);
        $second = $policy->plan(self::tools(), $first['tools'], array_merge(['editing' => false], $first['groups']));

        self::assertSame(['tools' => [], 'groups' => [], 'refused' => []], $second);
    }

    public function testEveryListedWriterIsListedOnce(): void
    {
        self::assertSame(ToolPolicy::APPROVED_WRITERS, array_values(array_unique(ToolPolicy::APPROVED_WRITERS)));
        self::assertSame([], array_intersect(ToolPolicy::APPROVED_WRITERS, ToolPolicy::READERS_SWITCHED_ON));
    }
}
