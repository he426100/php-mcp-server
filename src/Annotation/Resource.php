<?php

namespace He426100\McpServer\Annotation;

/**
 * Resource注解 - 用于标记MCP资源处理方法
 * 
 * @Annotation
 * @Target({"METHOD"})
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class Resource
{
    /**
     * @param string $uri 资源URI
     * @param string $name 资源名称
     * @param string $description 资源描述
     * @param string $mimeType 资源MIME类型
     */
    public function __construct(
        private string $uri,
        private string $name,
        private string $description,
        private string $mimeType = 'text/plain'
    ) {}

    public function getUri(): string 
    {
        return $this->uri;
    }

    public function getName(): string 
    {
        return $this->name;
    }

    public function getDescription(): string 
    {
        return $this->description;
    }

    public function getMimeType(): string 
    {
        return $this->mimeType;
    }
} 