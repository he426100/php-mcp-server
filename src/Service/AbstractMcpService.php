<?php

namespace He426100\McpServer\Service;

use He426100\McpServer\Annotation\McpHandler;
use Mcp\Server\Server;
use ReflectionClass;
use ReflectionMethod;

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

        foreach ($methods as $method) {
            $attributes = $method->getAttributes(McpHandler::class);
            
            if (empty($attributes)) {
                continue;
            }
            
            foreach ($attributes as $attribute) {
                $handler = $attribute->newInstance();
                $path = $handler->getPath();
                
                $server->registerHandler($path, [$this, $method->getName()]);
            }
        }
    }
} 