<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\AiStack;

/**
 * Which nr-llm tools the Netresearch chat may call in this lab.
 *
 * Reading tools keep the state nr-llm ships them in (almost all are on), plus
 * three readers the chat needs that ship off. The readers whose names end in
 * "_raw" (unredacted users, environment, phpinfo) and send_fal_file (hands a
 * file to a remote A2A client) stay off.
 *
 * Writing tools are off unless they are on the list below, and a listed one is
 * only switched on while nr-llm still makes every call of it wait for a
 * person's approval (ToolApprovalRule: a declared write, or the approval
 * marker). The list is the set netresearch/typo3-demo enables (its
 * data/seed-extensions.sql, step 8), limited to the tools this lab has.
 * There is no auto-approve switch in nr-llm, so an enabled writer can never do
 * more than the acting backend user may do by hand, and never without a click.
 *
 * Default deny: a writer that is not listed, or that loses its approval in a
 * later nr-llm release, is switched off; a tool nr-llm adds later keeps its
 * shipped default until it is listed here.
 */
final class ToolPolicy
{
    /** @var list<string> */
    public const APPROVED_WRITERS = [
        'attach_file_to_content_element',
        'attach_file_to_record',
        'clear_cache',
        'copy_record',
        'create_content_element_draft',
        'create_page_draft',
        'create_record_draft',
        'create_redirect',
        'create_translation_draft',
        'delete_record',
        'disable_redirect',
        'fetch_external_url',
        'move_content_element',
        'move_page',
        'publish_record',
        'replace_file_reference',
        'set_file_alternative_text',
        'set_file_categories',
        'set_file_reference_alternative_text',
        'set_file_reference_decorative',
        'set_page_social_image',
        'update_content_element',
        'update_fal_asset_meta',
        'update_page_metadata',
        'update_redirect',
    ];

    /**
     * Readers that ship off and that the chat uses: the reply buttons of a
     * guided tour (ask_choice), the element highlight and the page metadata
     * check.
     *
     * @var list<string>
     */
    public const READERS_SWITCHED_ON = [
        'ask_choice',
        'check_page_metadata',
        'highlight_record',
    ];

    /**
     * The overrides to write so that every tool ends up in its wanted state.
     * Only differences are returned: a tool whose current state (its override,
     * or its default without one) is already right produces nothing, so a
     * second run plans nothing.
     *
     * The tool groups of every tool that should be on are switched on too: a
     * disabled group hides all of its tools whatever their own state says.
     *
     * @param list<ToolDescriptor> $tools
     * @param array<string, bool> $toolOverrides tool name => enabled, as stored
     * @param array<string, bool> $groupOverrides group name => enabled, as stored
     * @return array{tools: array<string, bool>, groups: array<string, bool>, refused: list<string>}
     */
    public function plan(array $tools, array $toolOverrides, array $groupOverrides): array
    {
        $toolChanges = [];
        $groupChanges = [];
        $refused = [];
        foreach ($tools as $tool) {
            if ($tool->remote) {
                continue;
            }
            $wanted = $this->wanted($tool);
            if (in_array($tool->name, self::APPROVED_WRITERS, true) && !$tool->requiresApproval) {
                $refused[] = $tool->name;
            }
            $current = $toolOverrides[$tool->name] ?? $tool->enabledByDefault;
            if ($current !== $wanted) {
                $toolChanges[$tool->name] = $wanted;
            }
            if ($wanted && $tool->group !== '' && ($groupOverrides[$tool->group] ?? true) === false) {
                $groupChanges[$tool->group] = true;
            }
        }
        ksort($toolChanges);
        ksort($groupChanges);
        sort($refused);

        return ['tools' => $toolChanges, 'groups' => $groupChanges, 'refused' => $refused];
    }

    private function wanted(ToolDescriptor $tool): bool
    {
        if ($tool->requiresApproval) {
            return in_array($tool->name, self::APPROVED_WRITERS, true);
        }

        return $tool->enabledByDefault || in_array($tool->name, self::READERS_SWITCHED_ON, true);
    }
}
