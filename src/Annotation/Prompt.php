<?php

namespace He426100\McpServer\Annotation;

/**
 * Prompt注解 - 用于标记MCP提示模板处理方法
 * 
 * @Annotation
 * @Target({"METHOD"})
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class Prompt
{
    /**
     * @param string $name 提示模板名称
     * @param string $description 提示模板描述
     * @param array $arguments 参数定义，格式：['参数名' => ['description' => '描述', 'required' => true]]
     */
    public function __construct(
        private string $name,
        private string $description,
        private array $arguments = []
    ) {}

    public function getName(): string 
    {
        return $this->name;
    }

    public function getDescription(): string 
    {
        return $this->description;
    }

    public function getArguments(): array 
    {
        return $this->arguments;
    }
} 