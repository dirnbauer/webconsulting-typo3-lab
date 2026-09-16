<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Service;

use Hn\Agent\Service\ToolConverterService;
use Hn\McpServer\MCP\ToolRegistry;

/**
 * Preserve MCP schemas in OpenAI's function format: nest root combinators,
 * serialize empty schema maps as objects, and unwrap arguments for execution.
 */
final class AgentToolConverterService extends ToolConverterService
{
    /**
     * @return array<int, array{type: string, function: array{name: string, description: string, parameters: array<array-key, mixed>}}>
     */
    public function convertTools(ToolRegistry $toolRegistry): array
    {
        $tools = parent::convertTools($toolRegistry);
        foreach ($tools as &$tool) {
            $parameters = $this->normalizeSchemaObjects($tool['function']['parameters']);
            if ($this->requiresWrapper($parameters)) {
                $parameters = [
                    'type' => 'object',
                    'properties' => ['arguments' => $parameters],
                    'required' => ['arguments'],
                    'additionalProperties' => false,
                ];
            }
            $tool['function']['parameters'] = $parameters;
        }
        unset($tool);
        return $tools;
    }

    /**
     * @param string|array<string, mixed> $arguments
     * @return array{text: string, media: list<array{mime: string, data: string, filename?: string}>}
     */
    public function executeToolCall(ToolRegistry $toolRegistry, string $name, string|array $arguments): array
    {
        $schema = $toolRegistry->getTool($name)?->getSchema()['inputSchema'] ?? [];
        if (is_array($schema) && $this->requiresWrapper($schema)) {
            $decoded = is_string($arguments) ? json_decode($arguments, true) : $arguments;
            if (is_array($decoded) && isset($decoded['arguments']) && is_array($decoded['arguments'])) {
                $arguments = $decoded['arguments'];
            }
        }
        // Native initial-context calls already use the original flat arguments.
        return parent::executeToolCall($toolRegistry, $name, $arguments);
    }

    /** @param array<array-key, mixed> $schema */
    private function requiresWrapper(array $schema): bool
    {
        return array_intersect(['oneOf', 'anyOf', 'allOf', 'enum', 'const', 'not'], array_keys($schema)) !== [];
    }

    /**
     * Visit only schema positions, leaving array-valued defaults and enums intact.
     *
     * @param array<array-key, mixed> $schema
     * @return array<array-key, mixed>
     */
    private function normalizeSchemaObjects(array $schema): array
    {
        foreach (['properties', 'patternProperties', '$defs', 'definitions', 'dependentSchemas'] as $keyword) {
            if (isset($schema[$keyword]) && is_array($schema[$keyword])) {
                $schema[$keyword] = $schema[$keyword] === [] ? new \stdClass() : array_map(
                    $this->normalizeSubschema(...),
                    $schema[$keyword],
                );
            }
        }
        foreach (['items', 'additionalProperties', 'contains', 'propertyNames', 'not', 'if', 'then', 'else', 'unevaluatedProperties', 'unevaluatedItems'] as $keyword) {
            if (isset($schema[$keyword]) && is_array($schema[$keyword])) {
                $schema[$keyword] = $this->normalizeSubschema($schema[$keyword]);
            }
        }
        foreach (['oneOf', 'anyOf', 'allOf', 'prefixItems'] as $keyword) {
            if (isset($schema[$keyword]) && is_array($schema[$keyword])) {
                $schema[$keyword] = array_map(
                    $this->normalizeSubschema(...),
                    $schema[$keyword],
                );
            }
        }
        return $schema;
    }

    private function normalizeSubschema(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        return $value === [] ? new \stdClass() : $this->normalizeSchemaObjects($value);
    }
}
