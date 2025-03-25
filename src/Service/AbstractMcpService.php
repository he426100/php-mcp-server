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
     * 缓存已处理过的反射方法
     * @var array<string, ReflectionMethod>
     */
    private static array $reflectionCache = [];

    /**
     * 将服务处理器注册到MCP服务器
     *
     * @param Server $server MCP服务器实例
     * @return void
     */
    public function registerHandlers(Server $server): void
    {
        $reflectionClass = new ReflectionClass($this);
        
        // 处理各类注解
        [$tools, $toolMethodMap] = $this->processToolAnnotations($reflectionClass);
        [$prompts, $promptMethodMap] = $this->processPromptAnnotations($reflectionClass);
        [$resources, $resourceUriMap] = $this->processResourceAnnotations($reflectionClass);
        
        // 注册各类处理器
        $this->registerToolHandlers($server, $tools, $toolMethodMap, $reflectionClass);
        $this->registerPromptHandlers($server, $prompts, $promptMethodMap, $reflectionClass);
        $this->registerResourceHandlers($server, $resources, $resourceUriMap, $reflectionClass);
    }

    /**
     * 处理工具注解
     *
     * @param ReflectionClass $reflectionClass
     * @return array [工具定义数组, 工具名称到方法名的映射]
     */
    protected function processToolAnnotations(ReflectionClass $reflectionClass): array
    {
        $tools = [];
        $toolMethodMap = [];
        
        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $toolAttributes = $method->getAttributes(Tool::class);
            foreach ($toolAttributes as $attribute) {
                $tool = $attribute->newInstance();
                $tools[] = $this->createToolDefinition($tool, $method);
                $toolMethodMap[$tool->getName()] = $method->getName();
            }
        }
        
        return [$tools, $toolMethodMap];
    }

    /**
     * 处理提示模板注解
     *
     * @param ReflectionClass $reflectionClass
     * @return array [提示模板定义数组, 提示模板名称到方法名的映射]
     */
    protected function processPromptAnnotations(ReflectionClass $reflectionClass): array
    {
        $prompts = [];
        $promptMethodMap = [];
        
        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $promptAttributes = $method->getAttributes(Prompt::class);
            foreach ($promptAttributes as $attribute) {
                $prompt = $attribute->newInstance();
                $prompts[] = $this->createPromptDefinition($prompt, $method);
                $promptMethodMap[$prompt->getName()] = $method->getName();
            }
        }
        
        return [$prompts, $promptMethodMap];
    }

    /**
     * 处理资源注解
     *
     * @param ReflectionClass $reflectionClass
     * @return array [资源定义数组, 资源URI到方法名的映射]
     */
    protected function processResourceAnnotations(ReflectionClass $reflectionClass): array
    {
        $resources = [];
        $resourceUriMap = [];
        
        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $resourceAttributes = $method->getAttributes(Resource::class);
            foreach ($resourceAttributes as $attribute) {
                $resource = $attribute->newInstance();
                $resources[] = $this->createResourceDefinition($resource, $method);
                $resourceUriMap[$resource->getUri()] = $method->getName();
            }
        }
        
        return [$resources, $resourceUriMap];
    }

    /**
     * 注册工具相关处理器
     *
     * @param Server $server MCP服务器实例
     * @param array $tools 工具定义数组
     * @param array $toolMethodMap 工具名称到方法名的映射
     * @param ReflectionClass $reflectionClass 反射类
     * @return void
     */
    protected function registerToolHandlers(
        Server $server, 
        array $tools, 
        array $toolMethodMap, 
        ReflectionClass $reflectionClass
    ): void {
        if (empty($tools)) {
            return;
        }
        
        // 注册工具列表处理器
        $server->registerHandler('tools/list', function () use ($tools) {
            return new ListToolsResult($tools);
        });

        // 注册工具调用处理器
        $server->registerHandler('tools/call', function ($params) use ($tools, $reflectionClass, $toolMethodMap) {
            $name = $params->name;
            $arguments = [];
            if (isset($params->arguments)) {
                $arguments = json_decode(json_encode($params->arguments), true);
            }

            if (!isset($toolMethodMap[$name])) {
                return new CallToolResult(
                    content: [new TextContent(text: "Error: Unknown tool: {$name}")],
                    isError: true
                );
            }

            $methodName = $toolMethodMap[$name];
            
            try {
                $method = $this->getCachedMethod($reflectionClass, $methodName);
                $result = $this->executeMethodSafely($method, $arguments);
                
                return new CallToolResult(
                    content: [new TextContent(text: (string)$result)]
                );
            } catch (\Throwable $e) {
                return new CallToolResult(
                    content: [new TextContent(text: "Error: " . $e->getMessage())],
                    isError: true
                );
            }
        });
    }

    /**
     * 注册提示模板相关处理器
     *
     * @param Server $server MCP服务器实例
     * @param array $prompts 提示模板定义数组
     * @param array $promptMethodMap 提示模板名称到方法名的映射
     * @param ReflectionClass $reflectionClass 反射类
     * @return void
     */
    protected function registerPromptHandlers(
        Server $server, 
        array $prompts, 
        array $promptMethodMap, 
        ReflectionClass $reflectionClass
    ): void {
        if (empty($prompts)) {
            return;
        }
        
        // 注册提示模板列表处理器
        $server->registerHandler('prompts/list', function () use ($prompts) {
            return new ListPromptsResult($prompts);
        });

        // 注册提示模板获取处理器
        $server->registerHandler('prompts/get', function ($params) use ($prompts, $reflectionClass, $promptMethodMap) {
            $name = $params->name;
            $arguments = [];
            if (isset($params->arguments)) {
                $arguments = json_decode(json_encode($params->arguments), true);
            }

            if (!isset($promptMethodMap[$name])) {
                throw new \InvalidArgumentException("Unknown prompt: {$name}");
            }

            $methodName = $promptMethodMap[$name];
            
            try {
                $method = $this->getCachedMethod($reflectionClass, $methodName);
                $result = $this->executeMethodSafely($method, $arguments);

                // 找到对应的prompt定义以获取描述
                $promptDescription = '';
                foreach ($prompts as $prompt) {
                    if ($prompt->name === $name) {
                        $promptDescription = $prompt->description;
                        break;
                    }
                }

                return new GetPromptResult(
                    messages: [
                        new PromptMessage(
                            role: Role::ASSISTANT,
                            content: new TextContent(text: (string)$result)
                        )
                    ],
                    description: $promptDescription
                );
            } catch (\Throwable $e) {
                throw new \InvalidArgumentException("Error processing prompt: " . $e->getMessage(), 0, $e);
            }
        });
    }

    /**
     * 注册资源相关处理器
     *
     * @param Server $server MCP服务器实例
     * @param array $resources 资源定义数组
     * @param array $resourceUriMap 资源URI到方法名的映射
     * @param ReflectionClass $reflectionClass 反射类
     * @return void
     */
    protected function registerResourceHandlers(
        Server $server, 
        array $resources, 
        array $resourceUriMap, 
        ReflectionClass $reflectionClass
    ): void {
        if (empty($resources)) {
            return;
        }
        
        // 注册资源列表处理器
        $server->registerHandler('resources/list', function () use ($resources) {
            return new ListResourcesResult($resources);
        });

        // 注册资源读取处理器
        $server->registerHandler('resources/read', function ($params) use ($resources, $reflectionClass, $resourceUriMap) {
            $uri = $params->uri;

            if (!isset($resourceUriMap[$uri])) {
                throw new \InvalidArgumentException("Unknown resource: {$uri}");
            }

            $methodName = $resourceUriMap[$uri];
            
            // 找到对应的resource定义以获取mimeType
            $mimeType = 'text/plain';
            foreach ($resources as $resource) {
                if ($resource->uri === $uri) {
                    $mimeType = $resource->mimeType;
                    break;
                }
            }

            try {
                $method = $this->getCachedMethod($reflectionClass, $methodName);
                $content = $this->executeMethodSafely($method, []);
                
                return new ReadResourceResult(
                    contents: [
                        $this->processResourceContent((string)$content, $mimeType, $uri)
                    ]
                );
            } catch (\Throwable $e) {
                throw new \InvalidArgumentException("Error reading resource: " . $e->getMessage(), 0, $e);
            }
        });
    }

    /**
     * 获取缓存的反射方法
     *
     * @param ReflectionClass $class 反射类
     * @param string $methodName 方法名
     * @return ReflectionMethod 反射方法
     */
    private function getCachedMethod(ReflectionClass $class, string $methodName): ReflectionMethod
    {
        $className = $class->getName();
        $cacheKey = "$className::$methodName";
        
        if (!isset(self::$reflectionCache[$cacheKey])) {
            self::$reflectionCache[$cacheKey] = $class->getMethod($methodName);
        }
        
        return self::$reflectionCache[$cacheKey];
    }

    /**
     * 安全执行方法，统一异常处理
     *
     * @param ReflectionMethod $method 要执行的方法
     * @param array $arguments 参数数组
     * @return mixed 方法返回值
     * @throws \RuntimeException 执行异常时
     */
    protected function executeMethodSafely(ReflectionMethod $method, array $arguments)
    {
        try {
            return $method->invoke($this, ...$this->prepareArguments($method, $arguments));
        } catch (\Throwable $e) {
            // 记录错误
            error_log("Error executing method {$method->getName()}: " . $e->getMessage());
            throw new \RuntimeException("Error: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 从方法中提取参数信息
     *
     * @param ReflectionMethod $method 反射方法
     * @return array 参数信息数组
     */
    private function extractParametersFromMethod(ReflectionMethod $method): array
    {
        $parameters = [];
        foreach ($method->getParameters() as $param) {
            $type = $param->getType();
            $parameters[$param->getName()] = [
                'type' => $type ? $this->getTypeString($type) : 'string',
                'description' => '', // 默认空描述
                'required' => !$param->isOptional()
            ];
        }
        return $parameters;
    }

    /**
     * 将PHP类型转换为JSON Schema类型
     *
     * @param \ReflectionType $type PHP类型
     * @return string JSON Schema类型
     */
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

    /**
     * 准备方法参数并进行类型转换
     *
     * @param ReflectionMethod $method 反射方法
     * @param array $arguments 原始参数值
     * @return array 处理后的参数数组
     * @throws \InvalidArgumentException 参数无效时
     */
    private function prepareArguments(ReflectionMethod $method, array $arguments): array
    {
        // 验证输入参数
        foreach ($arguments as $name => $value) {
            if (!is_string($name)) {
                throw new \InvalidArgumentException("Invalid parameter name: must be string");
            }
        }
        
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

    /**
     * 根据参数类型对值进行转换
     *
     * @param mixed $value 原始值
     * @param ReflectionParameter $param 参数反射对象
     * @return mixed 转换后的值
     * @throws \InvalidArgumentException 类型转换失败时
     */
    private function convertValue($value, ReflectionParameter $param)
    {
        $type = $param->getType();
        if (!$type instanceof \ReflectionNamedType) {
            return $value;
        }

        try {
            return match ($type->getName()) {
                'int' => filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) 
                    ?? throw new \InvalidArgumentException("Invalid integer value"),
                'float' => filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) 
                    ?? throw new \InvalidArgumentException("Invalid float value"),
                'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                'string' => (string)$value,
                'array' => is_array($value) ? $value : (array)$value,
                default => $value
            };
        } catch (\TypeError $e) {
            throw new \InvalidArgumentException(
                "Cannot convert value to {$type->getName()}: {$e->getMessage()}"
            );
        }
    }

    /**
     * 处理资源内容，支持大文件处理
     *
     * @param string $content 资源内容
     * @param string $mimeType MIME类型
     * @param string $uri 资源URI
     * @return TextResourceContents 资源内容对象
     */
    protected function processResourceContent(string $content, string $mimeType, string $uri): TextResourceContents
    {
        // 对于大文件，可以使用分块处理或提示截断
        $maxSize = 2 * 1024 * 1024; // 2MB限制示例
        if (strlen($content) > $maxSize) {
            $truncatedContent = substr($content, 0, $maxSize);
            $truncatedContent .= "\n... (content truncated, full size: " . strlen($content) . " bytes)";
            
            return new TextResourceContents(
                uri: $uri,
                text: $truncatedContent,
                mimeType: $mimeType
            );
        }
        
        return new TextResourceContents(
            uri: $uri,
            text: $content,
            mimeType: $mimeType
        );
    }

    /**
     * 创建工具定义
     *
     * @param Tool $tool 工具注解
     * @param ReflectionMethod $method 工具方法
     * @return McpTool 工具定义
     */
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

    /**
     * 创建提示模板定义
     *
     * @param Prompt $prompt 提示模板注解
     * @param ReflectionMethod $method 提示模板方法
     * @return McpPrompt 提示模板定义
     */
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

    /**
     * 创建资源定义
     *
     * @param Resource $resource 资源注解
     * @param ReflectionMethod $method 资源方法
     * @return McpResource 资源定义
     */
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
