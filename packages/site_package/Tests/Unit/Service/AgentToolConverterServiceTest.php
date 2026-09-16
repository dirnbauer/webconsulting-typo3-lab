<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Service;

use Hn\McpServer\MCP\Tool\AbstractTool;
use Hn\McpServer\MCP\ToolRegistry;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use PHPUnit\Framework\TestCase;
use Webconsulting\SitePackage\Service\AgentToolConverterService;

final class AgentToolConverterServiceTest extends TestCase
{
    public function testAlternativeArgumentsKeepTheirFullSchemaAndReachTheOriginalTool(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => ['uid' => ['type' => 'integer'], 'url' => ['type' => 'string']],
            'oneOf' => [['required' => ['uid']], ['required' => ['url']]],
        ];
        $tool = $this->createMock(AbstractTool::class);
        $tool->method('getName')->willReturn('GetPage');
        $tool->method('getSchema')->willReturn(['description' => 'Read a page', 'inputSchema' => $schema]);
        $tool->expects(self::exactly(2))->method('execute')->with(['uid' => 505])
            ->willReturn(new CallToolResult([new TextContent('Desiderio')]));
        $registry = new ToolRegistry([$tool]);
        $converter = new AgentToolConverterService();

        $converted = $converter->convertTools($registry)[0]['function']['parameters'];
        self::assertArrayNotHasKey('oneOf', $converted);
        self::assertSame($schema, $converted['properties']['arguments']);
        self::assertSame(['arguments'], $converted['required']);
        self::assertFalse($converted['additionalProperties']);
        self::assertSame('Desiderio', $converter->executeToolCall($registry, 'GetPage', '{"arguments":{"uid":505}}')['text']);
        self::assertSame('Desiderio', $converter->executeToolCall($registry, 'GetPage', ['uid' => 505])['text']);
    }

    public function testOrdinaryToolSchemaAndArgumentsAreUnchanged(): void
    {
        $schema = ['type' => 'object', 'properties' => ['arguments' => ['type' => 'object']]];
        $arguments = ['arguments' => ['name' => 'example']];
        $tool = $this->createMock(AbstractTool::class);
        $tool->method('getName')->willReturn('Example');
        $tool->method('getSchema')->willReturn(['description' => 'Example', 'inputSchema' => $schema]);
        $tool->expects(self::once())->method('execute')->with($arguments)
            ->willReturn(new CallToolResult([new TextContent('unchanged')]));
        $registry = new ToolRegistry([$tool]);
        $converter = new AgentToolConverterService();

        self::assertSame($schema, $converter->convertTools($registry)[0]['function']['parameters']);
        self::assertSame('unchanged', $converter->executeToolCall($registry, 'Example', $arguments)['text']);
    }

    public function testEmptySchemaObjectsSerializeAsObjectsWithoutChangingArrayValues(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'empty' => ['type' => 'object', 'properties' => []],
                'anything' => [],
                'values' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => []], 'default' => [], 'enum' => [[]]],
            ],
        ];
        $tool = $this->createStub(AbstractTool::class);
        $tool->method('getName')->willReturn('Example');
        $tool->method('getSchema')->willReturn(['inputSchema' => $schema]);
        $converted = (new AgentToolConverterService())->convertTools(new ToolRegistry([$tool]))[0]['function']['parameters'];

        $json = json_encode($converted, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('"empty":{"type":"object","properties":{}}', $json);
        self::assertStringContainsString('"anything":{}', $json);
        self::assertStringContainsString('"items":{"type":"object","properties":{}}', $json);
        self::assertSame([], $converted['properties']['values']['default']);
        self::assertSame([[]], $converted['properties']['values']['enum']);
    }
}
