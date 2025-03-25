<?php

namespace He426100\McpServer\Annotation;

/**
 * MCP处理器注解
 * 
 * @Annotation
 * @Target({"METHOD"})
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class McpHandler
{
    /**
     * 处理器路径
     *
     * @var string
     */
    private string $path;

    /**
     * 构造函数
     *
     * @param string $path 处理器路径
     */
    public function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * 获取处理器路径
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }
}
