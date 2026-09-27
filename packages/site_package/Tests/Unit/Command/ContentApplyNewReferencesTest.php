<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Webconsulting\SitePackage\Command\ContentApplyCommand;

/**
 * DataHandler processes pages before every other table. A post created in
 * the same run as its new tags and categories lost those relations (no MM
 * row, a count of 0), and an existing post lost a newly created tag. Such
 * values wait for a second pass, when every record has its uid.
 */
final class ContentApplyNewReferencesTest extends TestCase
{
    public function testARelationToACreatedRecordWaitsForTheSecondPass(): void
    {
        [$immediate, $deferred] = ContentApplyCommand::splitNewReferences([
            'title' => 'Easy Workspace',
            'categories' => 'NEW_blog_category_editing',
            'tags' => '275,23,NEW_blog_tag_accessibility,274',
            'authors' => '12',
        ], ['NEW_blog_category_editing', 'NEW_blog_tag_accessibility']);

        self::assertSame(['title' => 'Easy Workspace', 'authors' => '12'], $immediate);
        self::assertSame([
            'categories' => 'NEW_blog_category_editing',
            'tags' => '275,23,NEW_blog_tag_accessibility,274',
        ], $deferred);
    }

    public function testTheParentStaysInTheFirstPass(): void
    {
        [$immediate, $deferred] = ContentApplyCommand::splitNewReferences(
            ['pid' => 'NEW_blog_post_workspace', 'header' => 'Publish a page'],
            ['NEW_blog_post_workspace']
        );

        self::assertSame(['pid' => 'NEW_blog_post_workspace', 'header' => 'Publish a page'], $immediate);
        self::assertSame([], $deferred);
    }

    public function testTextThatMentionsNewIsNotAReference(): void
    {
        [$immediate, $deferred] = ContentApplyCommand::splitNewReferences(
            ['title' => 'NEW: two blog templates', 'subtitle' => 'NEW_blog_tag_accessibility is a key'],
            ['NEW_blog_tag_accessibility']
        );

        self::assertSame([], $deferred);
        self::assertCount(2, $immediate);
    }

    public function testWithoutCreatedRecordsNothingWaits(): void
    {
        [$immediate, $deferred] = ContentApplyCommand::splitNewReferences(['tags' => 'NEW_x'], []);

        self::assertSame(['tags' => 'NEW_x'], $immediate);
        self::assertSame([], $deferred);
    }

    public function testPlaceholdersBecomeUidsAndKeepTheirSign(): void
    {
        self::assertSame(
            '275,23,293,-294,274',
            ContentApplyCommand::substituteNewIds('275, 23,NEW_blog_tag_accessibility,-NEW_blog_tag_x,274', [
                'NEW_blog_tag_accessibility' => 293,
                'NEW_blog_tag_x' => '294',
            ])
        );
    }

    public function testAPlaceholderDataHandlerDidNotCreateStaysAsItIs(): void
    {
        self::assertSame('NEW_missing,5', ContentApplyCommand::substituteNewIds('NEW_missing,5', []));
    }
}
