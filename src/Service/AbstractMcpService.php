<?php

namespace He426100\McpServer\Service;

use He426100\McpServer\Annotation\Tool;
use He426100\McpServer\Annotation\Prompt;
use He426100\McpServer\Annotation\Resource;
use Mcp\Server\Server;
use Mcp\Types\Tool as McpTool;
use Mcp\Types\Prompt as McpPrompt;
use Mcp\Types\Resource as McpResource;
use Mcp\Types\ToolInputSchema;
use Mcp\Types\ToolInputProperties;
use Mcp\Types\ListToolsResult;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Mcp\Types\PromptArgument;
use Mcp\Types\ListPromptsResult;
use Mcp\Types\GetPromptResult;
use Mcp\Types\PromptMessage;
use Mcp\Types\ListResourcesResult;
use Mcp\Types\ReadResourceResult;
use Mcp\Types\TextResourceContents;
use Mcp\Types\Role;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;

abstract class AbstractMcpService
{
    /**
     * 将服务处理器注册到MCP服务器
     *
     * @param Server $server MCP服务器实例
     * @return void
     */
    public function registerHandlers(Server $server): void
    {
        $reflectionClass = new ReflectionClass($this);
        $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

        $tools = [];
        $prompts = [];
        $resources = [];

        foreach ($methods as $method) {
            // 处理Tool注解
            $toolAttributes = $method->getAttributes(Tool::class);
            foreach ($toolAttributes as $attribute) {
                $tool = $attribute->newInstance();
                $tools[] = $this->createToolDefinition($tool, $method);
            }

            // 处理Prompt注解
            $promptAttributes = $method->getAttributes(Prompt::class);
            foreach ($promptAttributes as $attribute) {
                $prompt = $attribute->newInstance();
                $prompts[] = $this->createPromptDefinition($prompt, $method);
            }

            // 处理Resource注解
            $resourceAttributes = $method->getAttributes(Resource::class);
            foreach ($resourceAttributes as $attribute) {
                $resource = $attribute->newInstance();
                $resources[] = $this->createResourceDefinition($resource, $method);
            }
        }

        // 注册工具列表处理器
        if (!empty($tools)) {
            $server->registerHandler('tools/list', function() use ($tools) {
                return new ListToolsResult($tools);
            });

            // 注册工具调用处理器
            $server->registerHandler('tools/call', function($params) use ($tools, $reflectionClass) {
                $name = $params->name;
                $arguments = $params->arguments ?? [];

                foreach ($tools as $tool) {
                    if ($tool->name === $name) {
                        $method = $reflectionClass->getMethod($tool->name);
                        try {
                            $result = $method->invoke($this, ...$this->prepareArguments($method, $arguments));
                            return new CallToolResult(
                                content: [new TextContent(text: (string)$result)]
                            );
                        } catch (\Throwable $e) {
                            return new CallToolResult(
                                content: [new TextContent(text: "Error: " . $e->getMessage())],
                                isError: true
                            );
                        }
                    }
                }

                throw new \InvalidArgumentException("Unknown tool: {$name}");
            });
        }

        // 注册提示模板列表处理器
        if (!empty($prompts)) {
            $server->registerHandler('prompts/list', function() use ($prompts) {
                return new ListPromptsResult($prompts);
            });

            // 注册提示模板获取处理器
            $server->registerHandler('prompts/get', function($params) use ($prompts, $reflectionClass) {
                $name = $params->name;
                $arguments = $params->arguments ?? new \stdClass();

                foreach ($prompts as $prompt) {
                    if ($prompt->name === $name) {
                        $method = $reflectionClass->getMethod($prompt->name);
                        try {
                            $result = $method->invoke($this, ...$this->prepareArguments($method, (array)$arguments));
                            return new GetPromptResult(
                                messages: [
                                    new PromptMessage(
                                        role: Role::ASSISTANT,
                                        content: new TextContent(text: (string)$result)
                                    )
                                ],
                                description: $prompt->description
                            );
                        } catch (\Throwable $e) {
                            throw new \InvalidArgumentException("Error processing prompt: " . $e->getMessage());
                        }
                    }
                }

                throw new \InvalidArgumentException("Unknown prompt: {$name}");
            });
        }

        // 注册资源列表处理器
        if (!empty($resources)) {
            $server->registerHandler('resources/list', function() use ($resources) {
                return new ListResourcesResult($resources);
            });

            // 注册资源读取处理器
            $server->registerHandler('resources/read', function($params) use ($resources, $reflectionClass) {
                $uri = $params->uri;

                foreach ($resources as $resource) {
                    if ($resource->uri === $uri) {
                        $method = $reflectionClass->getMethod($resource->name);
                        try {
                            $content = $method->invoke($this);
                            return new ReadResourceResult(
                                contents: [
                                    new TextResourceContents(
                                        uri: $uri,
                                        text: (string)$content,
                                        mimeType: $resource->mimeType
                                    )
                                ]
                            );
                        } catch (\Throwable $e) {
                            throw new \InvalidArgumentException("Error reading resource: " . $e->getMessage());
                        }
                    }
                }

                throw new \InvalidArgumentException("Unknown resource: {$uri}");
            });
        }
    }

    private function createToolDefinition(Tool $tool, ReflectionMethod $method): McpTool
    {
        $parameters = $tool->getParameters();
        if (empty($parameters)) {
            $parameters = $this->extractParametersFromMethod($method);
        }

        $properties = ToolInputProperties::fromArray($parameters);
        $required = array_keys(array_filter($parameters, fn($p) => ($p['required'] ?? false)));

        return new McpTool(
            name: $tool->getName(),
            description: $tool->getDescription(),
            inputSchema: new ToolInputSchema(
                properties: $properties,
                required: $required
            ),
        );
    }

    private function extractParametersFromMethod(ReflectionMethod $method): array
    {
        $parameters = [];
        foreach ($method->getParameters() as $param) {
            $type = $param->getType();
            $parameters[$param->getName()] = [
                'type' => $type ? $this->getTypeString($type) : 'string',
                'description' => '',
                'required' => !$param->isOptional()
            ];
        }
        return $parameters;
    }

    private function getTypeString(\ReflectionType $type): string
    {
        if ($type instanceof \ReflectionNamedType) {
            return match ($type->getName()) {
                'int', 'float' => 'number',
                'bool' => 'boolean',
                'array' => 'object',
                default => 'string'
            };
        }
        return 'string';
    }

    private function prepareArguments(ReflectionMethod $method, array $arguments): array
    {
        $preparedArgs = [];
        foreach ($method->getParameters() as $param) {
            $name = $param->getName();
            if (isset($arguments[$name])) {
                $preparedArgs[] = $this->convertValue($arguments[$name], $param);
            } elseif ($param->isOptional()) {
                $preparedArgs[] = $param->getDefaultValue();
            } else {
                throw new \InvalidArgumentException("Missing required parameter: {$name}");
            }
        }
        return $preparedArgs;
    }

    private function convertValue($value, ReflectionParameter $param)
    {
        $type = $param->getType();
        if (!$type instanceof \ReflectionNamedType) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => (int)$value,
            'float' => (float)$value,
            'bool' => (bool)$value,
            'string' => (string)$value,
            default => $value
        };
    }

    private function createPromptDefinition(Prompt $prompt, ReflectionMethod $method): McpPrompt
    {
        $arguments = $prompt->getArguments();
        if (empty($arguments)) {
            $arguments = $this->extractParametersFromMethod($method);
        }

        $promptArguments = [];
        foreach ($arguments as $name => $config) {
            $promptArguments[] = new PromptArgument(
                name: $name,
                description: $config['description'] ?? '',
                required: $config['required'] ?? true
            );
        }

        return new McpPrompt(
            name: $prompt->getName(),
            description: $prompt->getDescription(),
            arguments: $promptArguments,
        );
    }

    private function createResourceDefinition(Resource $resource, ReflectionMethod $method): McpResource
    {
        return new McpResource(
            uri: $resource->getUri(),
            name: $resource->getName(),
            description: $resource->getDescription(),
            mimeType: $resource->getMimeType(),
        );
    }
} 