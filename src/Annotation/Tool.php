<?php

namespace He426100\McpServer\Annotation;

/**
 * Tool注解 - 用于标记MCP工具处理方法
 * 
 * @Annotation
 * @Target({"METHOD"})
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class Tool
{
    /**
     * @param string $name 工具名称
     * @param string $description 工具描述
     * @param array $parameters 参数定义，格式：['参数名' => ['type' => '类型', 'description' => '描述', 'required' => true]]
     */
    public function __construct(
        private string $name,
        private string $description,
        private array $parameters = []
    ) {}

    public function getName(): string 
    {
        return $this->name;
    }

    public function getDescription(): string 
    {
        return $this->description;
    }

    public function getParameters(): array 
    {
        return $this->parameters;
    }
} 