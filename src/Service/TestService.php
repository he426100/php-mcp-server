<?php

namespace He426100\McpServer\Service;

use He426100\McpServer\Annotation\McpHandler;
use Mcp\Server\Server;
use Mcp\Types\CallToolResult;
use Mcp\Types\GetPromptRequestParams;
use Mcp\Types\GetPromptResult;
use Mcp\Types\ListPromptsResult;
use Mcp\Types\ListResourcesResult;
use Mcp\Types\ListToolsResult;
use Mcp\Types\Prompt;
use Mcp\Types\PromptArgument;
use Mcp\Types\PromptMessage;
use Mcp\Types\ReadResourceResult;
use Mcp\Types\Resource;
use Mcp\Types\Role;
use Mcp\Types\TextContent;
use Mcp\Types\TextResourceContents;
use Mcp\Types\Tool;
use Mcp\Types\ToolInputProperties;
use Mcp\Types\ToolInputSchema;
use ReflectionClass;
use ReflectionMethod;

class TestService extends AbstractMcpService
{
    /**
     * 列出可用提示模板
     *
     * @param mixed $params
     * @return ListPromptsResult
     */
    #[McpHandler('prompts/list')]
    public function listPrompts($params): ListPromptsResult
    {
        $prompt = new Prompt(
            name: 'example-prompt',
            description: 'An example prompt template',
            arguments: [
                new PromptArgument(
                    name: 'arg1',
                    description: 'Example argument',
                    required: true
                )
            ]
        );
        return new ListPromptsResult([$prompt]);
    }

    /**
     * 获取指定提示模板
     *
     * @param GetPromptRequestParams $params
     * @return GetPromptResult
     */
    #[McpHandler('prompts/get')]
    public function getPrompt(GetPromptRequestParams $params): GetPromptResult
    {
        $name = $params->name;
        $arguments = $params->arguments;
        if ($name !== 'example-prompt') {
            throw new \InvalidArgumentException("Unknown prompt: {$name}");
        }
        // Get argument value safely
        $argValue = $arguments ? $arguments->arg1 : 'none';
        $prompt = new Prompt(
            name: 'example-prompt',
            description: 'An example prompt template',
            arguments: [
                new PromptArgument(
                    name: 'arg1',
                    description: 'Example argument',
                    required: true
                )
            ]
        );
        return new GetPromptResult(
            messages: [
                new PromptMessage(
                    role: Role::USER,
                    content: new TextContent(
                        text: "Example prompt text with argument: $argValue"
                    )
                )
            ],
            description: 'Example prompt'
        );
    }

    /**
     * 列出可用工具
     *
     * @param mixed $params
     * @return ListToolsResult
     */
    #[McpHandler('tools/list')]
    public function listTools($params): ListToolsResult
    {
        // Create properties object first
        $properties = ToolInputProperties::fromArray([
            'num1' => [
                'type' => 'number',
                'description' => 'First number'
            ],
            'num2' => [
                'type' => 'number',
                'description' => 'Second number'
            ]
        ]);

        // Create schema with properties and required fields
        $inputSchema = new ToolInputSchema(
            properties: $properties,
            required: ['num1', 'num2']
        );

        $tool = new Tool(
            name: 'add-numbers',
            description: 'Adds two numbers together',
            inputSchema: $inputSchema
        );

        return new ListToolsResult([$tool]);
    }

    /**
     * 调用指定工具
     *
     * @param mixed $params
     * @return CallToolResult
     */
    #[McpHandler('tools/call')]
    public function callTool($params): CallToolResult
    {
        $name = $params->name;
        $arguments = $params->arguments ?? [];

        if ($name !== 'add-numbers') {
            throw new \InvalidArgumentException("Unknown tool: {$name}");
        }

        // Validate and convert arguments to numbers
        $num1 = filter_var($arguments['num1'] ?? null, FILTER_VALIDATE_FLOAT);
        $num2 = filter_var($arguments['num2'] ?? null, FILTER_VALIDATE_FLOAT);

        if ($num1 === false || $num2 === false) {
            return new CallToolResult(
                content: [new TextContent(
                    text: "Error: Both arguments must be valid numbers"
                )],
                isError: true
            );
        }

        $sum = $num1 + $num2;
        return new CallToolResult(
            content: [new TextContent(
                text: "The sum of {$num1} and {$num2} is {$sum}"
            )]
        );
    }

    /**
     * 列出可用资源
     *
     * @param mixed $params
     * @return ListResourcesResult
     */
    #[McpHandler('resources/list')]
    public function listResources($params): ListResourcesResult
    {
        $resource = new Resource(
            uri: 'example://greeting',
            name: 'Greeting Text',
            description: 'A simple greeting message',
            mimeType: 'text/plain'
        );
        return new ListResourcesResult([$resource]);
    }

    /**
     * 读取指定资源
     *
     * @param mixed $params
     * @return ReadResourceResult
     */
    #[McpHandler('resources/read')]
    public function readResource($params): ReadResourceResult
    {
        $uri = $params->uri;
        if ($uri !== 'example://greeting') {
            throw new \InvalidArgumentException("Unknown resource: {$uri}");
        }

        return new ReadResourceResult(
            contents: [new TextResourceContents(
                uri: $uri,
                text: "Hello from the example MCP server!",
                mimeType: 'text/plain'
            )]
        );
    }
}
